<?php

namespace Home\Model;

class MoldProdModel extends WeldProdModel
{
    protected $baseConds = [
            'comp_pm_code' => 'L',
            'comp_status'  => 'AC',
    ];
    protected $leadDayLen = 1;
 
    
    protected $machCaps = []; 
 
    protected $_sodModel;
    
    
    protected function getDmdModel ()
    {
        if (empty($this->_dmdModel)) {
            $this->_dmdModel = M('mold_dmd');
        }
    
        return $this->_dmdModel;
    }
    
    protected function getSodModel ()
    {
        if (empty($this->_sodModel)) {
            $this->_sodModel = M('mold_sod');
        }
        
        return $this->_sodModel;
    }
    
    protected function getOrderBy ()
    {
        //return "comp_part";
    }
    
    public function __construct($site = '')
    {
 
    
        if ($site) {
            $site = strval($site);
            $this->addConds([
                    "par_site" => $site
            ]);
            
            switch ($site) {
                case 1000:
                    $this->_proj = 'cd539';
                    $this->addConds([
                            "comp_promo" => 'cd539',
                            "comp_line"  => 'M00001'
                    ]);
                    break;
                case 6000:
                    $this->addConds([
                            "comp_line" => 'M60001'
                    ]);
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
        
            $orgDate = $this->getOrgDate();


            $result = $this->getDmdModel()->where($conds)->order($this->getOrderBy())->select();
            $compsInfo = [];
            foreach ($result as $row) {
                $comp = $row["comp_part"];
                $par  = $row["par_part"];
                $dmdDate = $row["dmd_date"];
                $planDate = $row["plan_date"];  // 根据视图的设计，这两个日期采用了非匹配读取，因为，可能有父物料需求日期而不存在子物料计划日期，也可能存在子物料计划日期而不存在父物料需求日期
                $planMach = $row["plan_mach"];
                $allowedmach = $row["mold_mach"];
                $proj = $row["mold_proj"];
                
                if (!isset($this->machCaps[$allowedmach][$comp])) {
                    $this->machCaps[$allowedmach][$comp] = floatval($row["mold_cap"]);
                }
                
                if (!isset($this->partsInfo[$comp])) {
                    $this->partsInfo[$comp] = [
                            'isMrp'    => (bool)$row["comp_isdmrp"],
                            'part'     => $row["comp_part"],
                            'site'     => $row["comp_site"],
                            'desc1'    => $row["comp_desc1"],
                            'desc2'    => $row["comp_desc2"],
                            'buyer'    => $row["comp_buyer"],
                            'line'     => $row["comp_line"],
                            'rop'      => floatval($row["comp_rop"]),
                            'ordMin'   => floatval($row["comp_ord_min"]),
                            
                            'allowedMachines' => [],
                            'projs'    => [],
                            'dir'      => $row["mold_lr"],
                            
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
                    if ($planDate !== null && !isset($this->partsInfo[$comp]['prods'][$planDate])) {
                        $this->partsInfo[$comp]['prods'][$planDate] =  floatval($row['plan_qty']);
                    }
                    if ($planMach !== null && !isset($this->partsInfo[$comp]['planMach'])) {
                        $this->partsInfo[$comp]['planMach'] =  $planMach;
                    }
                }
                
                if (!isset($this->partsInfo[$comp]["allowedMachines"][$allowedmach])) {
                    $this->partsInfo[$comp]["allowedMachines"][$allowedmach] = $allowedmach;
                }
                
                if (!isset($this->partsInfo[$comp]["projs"][$proj])) {
                    $this->partsInfo[$comp]["projs"][$proj] = $proj;
                }
        
                if (!isset($this->partsInfo[$comp]["stocks"][$orgDate]) && $row["in_qty_oh"] && $row["in_date"] == $this->getStartActiveDate()) {
                    $this->partsInfo[$comp]["stocks"][$orgDate] = floatval($row["in_qty_oh"]);
                }
        
                if (!isset($this->partsInfo[$comp]["parDmds"][$dmdDate][$par]) && floatval($row["dmd_qty"])) {
                    $this->partsInfo[$comp]["parDmds"][$dmdDate][$par] = floatval($row["dmd_qty"]);
                }
        
                if (!isset($this->partsInfo[$comp]["parYldPcts"][$par])) {
                    $this->partsInfo[$comp]["parYldPcts"][$par] = floatval($row["par_yld_pct"]) / 100;
                }
        
                if (!isset($this->partsInfo[$comp]["qtyPers"][$par])) {
                    $this->partsInfo[$comp]["qtyPers"][$par] = floatval($row["ps_qty_per"]);
                }
        
            }
        
 
            foreach ($this->partsInfo as $part => &$partInfo) {
                if (empty($partInfo["planMach"]) && count($partInfo["allowedMachines"]) == 1) {
                    $partInfo["planMach"] = $partInfo["allowedMachines"][0];
                }
                
                $partInfo["projs"] = array_values($partInfo["projs"]);
                foreach ($this->getActiveDates() as $date) {
                    foreach ($partInfo['parDmds'][$date] as $par => $pdmd) {
                        //$partInfo['dmds'][$date] += ceil($pdmd / $partInfo['parYldPcts'][$par] * $partInfo['qtyPers'][$par]);
                        $partInfo['dmds'][$date] += $pdmd  * $partInfo['qtyPers'][$par];
                    }
                    
                    $partInfo["availCap"][$date] = $partInfo["cap"];
                }
                

            }
            
            

 
            $result = $this->getSodModel()->where($conds)->select();
            $compsInfo = [];
            foreach ($result as $row) {
                $comp = $row["comp_part"];
                $par  = $row["par_part"];
                $dmdDate = $row["dmd_date"];
                $planDate = $row["plan_date"];  // 根据视图的设计，这两个日期采用了非匹配读取，因为，可能有父物料需求日期而不存在子物料计划日期，也可能存在子物料计划日期而不存在父物料需求日期
                $planMach = $row["plan_mach"];
                $allowedmach = $row["mold_mach"];
                $proj = $row["mold_proj"];
                if (!isset($this->machCaps[$allowedmach][$comp])) {
                    $this->machCaps[$allowedmach][$comp] = floatval($row["mold_cap"]);
                }
                
                if (!isset($this->partsInfo[$comp])) {
                    $this->partsInfo[$comp] = [
                            'isMrp'    => (bool)$row["comp_isdmrp"],
                            'part'     => $row["comp_part"],
                            'site'     => $row["comp_site"],
                            'desc1'    => $row["comp_desc1"],
                            'desc2'    => $row["comp_desc2"],
                            'buyer'    => $row["comp_buyer"],
                            'line'     => $row["comp_line"],
                            'rop'      => floatval($row["comp_rop"]),
                            'ordMin'   => floatval($row["comp_ord_min"]),
                
                            'allowedMachines' => [],
                            'projs'    => [],
                            'dir'      => $row["mold_lr"],
                            
                            
                            'sodDmds'     => [],
                            'stocks'   => []
                    ];
                    
                }
                
                // 如果子自制件不需要重新进行mrp，直接读取对应的生产计划数据即可
                if (!$this->partsInfo[$comp]["isMrp"]) {
                    // 已有某日期的计划数据时才进行记录
                    if ($planDate !== null && !isset($this->partsInfo[$comp]['prods'][$planDate])) {
                        $this->partsInfo[$comp]['prods'][$planDate] =  floatval($row['plan_qty']);
                    }
                    if ($planMach !== null && !isset($this->partsInfo[$comp]['planMach'])) {
                        $this->partsInfo[$comp]['planMach'] =  $planMach;
                    }
                }
                

                if (!isset($this->partsInfo[$comp]["allowedMachines"][$allowedmach])) {
                    $this->partsInfo[$comp]["allowedMachines"][$allowedmach] = $allowedmach;
                }
                
                if (!isset($this->partsInfo[$comp]["projs"][$proj])) {
                    $this->partsInfo[$comp]["projs"][$proj] = $proj;
                }
                
                if (!isset($this->partsInfo[$comp]["stocks"][$orgDate]) && $row["in_qty_oh"] && $row["in_date"] == $this->getStartActiveDate()) {
                    $this->partsInfo[$comp]["stocks"][$orgDate] = floatval($row["in_qty_oh"]);
                }
                
                if (!isset($this->partsInfo[$comp]["sodDmds"][$dmdDate]) && floatval($row["dmd_qty"])) {
                    $this->partsInfo[$comp]["sodDmds"][$dmdDate] = floatval($row["dmd_qty"]);
                }
                
            }
            
            
            // 叠加上级物料分解和直接订单数量
            foreach ($this->partsInfo as $part => &$partInfo) {
 
                $partInfo["allowedMachines"] = array_values($partInfo["allowedMachines"]);
                foreach ($this->getActiveDates() as $date) {
                    if ($partInfo["sodDmds"][$date]) {
                        $partInfo["dmds"][$date] += $partInfo["sodDmds"][$date];
                    }
                }
                
                
            }
            
          

        }
         

        return $this->partsInfo;
    }
    
    public function getMachCaps()
    {
        return $this->machCaps;
    }
    

    
    

    public function doEasyMrp ()
    {
        $parts = $this->getPartsInfo();
        if (empty($parts)) {
            return;
        }

        
        $allParts = array_keys($parts);
        $mrpedParts = $this->getMrpedParts();
        $unmrpedparts = $this->getUnmrpedParts();
        
        foreach ($this->activeDates as $curDate) {
            foreach ($mrpedParts as $part) {
                if ($this->isDayDate($curDate)) {
                    $this->availProjDirDailyCaps[$proj][$dir][$curDate] -= $this->partsInfo[$part]["prods"][$curDate];
                } else {
                    // 830油漆计划一般不被导入，这时候直接算出来
                    if (empty($this->partsInfo[$part]["prods"][$curDate])) {
                        $this->partsInfo[$part]["prods"][$curDate] = $this->partsInfo[$part]["dmds"][$curDate];
                    }
                }
            
                $this->calculatePartStockOfDate($part, $curDate);
            }
            
            
            if ($this->isDayDate($curDate)) {
                // 862只安排手动排产即可
            } else {
                foreach ($mrpedParts as $part) {
                    
                }
                foreach ($unmrpedparts as $part) {
                    $this->partsInfo[$part]["prods"][$curDate] = ceil($this->partsInfo[$part]["dmds"][$curDate] / $this->partsInfo[$part]["ordMin"]) * $this->partsInfo[$part]["ordMin"];
                }
        
            }
        
        }
        
        
        $this->calculateAllPartsStock();
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
            foreach ($mrpedParts as $part) {
                if ($this->isDayDate($curDate)) {
                    $this->availDayCapacities[$curDate] -= $this->partsInfo[$part]["prods"][$curDate];
                } else {
                    if (empty($this->partsInfo[$part]["prods"][$curDate])) {
                        $this->partsInfo[$part]["prods"][$curDate] = $this->partsInfo[$part]["dmds"][$curDate];
                    }
                }
            }
    
            if ($this->isDayDate($curDate)) {
                if ($this->isWorkday($curDate)) {
                    $prevDate = \DateHelper::getDateBefore($curDate);
                    $nextDate = \DateHelper::getDateAfter($curDate);
                    foreach ($unmrpedparts as $part) {
                        $prevStock = $this->partsInfo[$part]["stocks"][$prevDate];
                        $curDmd = $this->partsInfo[$part]["dmds"][$curDate];
                        $curProd = $this->partsInfo[$part]["prods"][$curDate];
                        $relatedDmd = $this->getDmdAfterLeadedDay($part, $curDate);
                        $rop = $this->partsInfo[$part]["rop"];
    
                        $curNetDmd = $curDmd + $relatedDmd + $rop - $curProd - $prevStock ;
                        


                        if ($curNetDmd > 0) {
                            $this->arrangeProduction($part, $curDate, $curNetDmd);
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
    
    public function arrangeProduction($part, $prodDate, $curNetDmd)
    {
        $ordMin = $this->partsInfo[$part]["ordMin"];
 
    
        $leftCapacity = $this->partsInfo[$part]['availCap'][$prodDate];
    
//         if ($part == '03.01.0229') {
//             var_dump($prodDate, $curNetDmd);
//             echo "<hr />";
//         }
    
        if ($leftCapacity >= $curNetDmd) {
            $this->partsInfo[$part]["prods"][$prodDate] += $curNetDmd;
            $this->partsInfo[$part]['availCap'][$prodDate] -= $curNetDmd;
        } else if ($prodDate == $this->getStartActiveDate()) {
            $this->partsInfo[$part]["prods"][$prodDate] += $curNetDmd;
            $this->partsInfo[$part]['availCap'][$prodDate] -= $curNetDmd;
        } else  {

           
            $this->partsInfo[$part]["prods"][$prodDate] += $leftCapacity;
            $this->partsInfo[$part]['availCap'][$prodDate] -= $leftCapacity;
            
            $curNetDmd -= $leftCapacity;
    
            // 然后，还需将可能残存的需求部分，移到前一个"可生产日"进行生产(该步骤可递归进行)
            $preProdDate = $this->getClosestWorkdayDate($prodDate, false);
            // 当某零件进行当日的额外最小量回溯排产，设置回溯标志为true,这部分回溯的需求量，是强制性的。
    
            //$this->arrangeProduction($part, $preProdDate, $curNetDmd);
    
    
        }
    }
    
    
    public function exportBalanceExcel ($proc = '生产')
    {
        $this->DoAssemblyMrp();
    
        import("Org.Util.PHPExcel");
        import("Org.Util.PHPExcel.Writer.Excel5");
        import("Org.Util.PHPExcel.IOFactory.php");
        $objPHPExcel = new \PHPExcel();
        $objActSheet = $objPHPExcel->getActiveSheet();
        $objActSheet->setTitle("$this->_proj {$proc}平衡表");
    
        $headers = ["零件", "产线", "机台", "班产", "信息", "类型"];
        $activeDates = $this->getActiveDates();
        $orgDate = $this->getOrgDate();
        $headers = array_merge($headers, $activeDates);
    
    
        $prefix = '';
        $j = "A";
        $colChars = [];
        foreach ($headers as $header) {
            $objActSheet->setCellValue($prefix . $j . '2', $header);
            $colChars[] = $prefix . $j;
    
            $j = chr(ord($j) + 1);
            if ($j > "Z") {
                $j =  'A';
                if ($prefix == '') {
                    $prefix =  'A';
                } else {
                    $prefix = chr(ord($prefix) + 1);
                }
            }
    
        }
    
    
        $logoStartCell = 'A1';
        $logoEndCell = end($colChars) . '1';
    
        $objActSheet
        ->mergeCells("$logoStartCell:$logoEndCell")
        ->setCellValue($logoStartCell, "$this->_proj-{$proc}平衡表");
        $logoCellStyle = $objActSheet->getStyle($logoStartCell);
        $logoCellStyle->getFont()->setName("微软雅黑")->setBold(true)->setSize(30)
        ->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_BLUE);
        $logoCellStyle->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    
        foreach ($colChars as $col) {
            $objActSheet->getColumnDimension($col . '2')->setAutoSize(true);
        }
    
        $row = 3;
        foreach ($this->partsInfo as $part => $partInfo) {
            // 用6行来写入一个零件
    
            // 用若干合并的6列来写非日期相关信息：
            $objActSheet
            ->mergeCells("A$row:A" . ($row + 5))
            ->setCellValueExplicit("A$row", $part)
            ->mergeCells("B$row:B" . ($row + 5))
            ->setCellValueExplicit("B$row", $partInfo["line"])
            ->mergeCells("C$row:C" . ($row + 5))
            ->setCellValueExplicit("C$row", $partInfo["planMach"])
            ->mergeCells("D$row:D" . ($row + 5))
            ->setCellValueExplicit("D$row", $this->machCaps[$partInfo["planMach"]][$part])
    
            ->setCellValueExplicit("E" . $row, $partInfo["desc1"])
            ->setCellValueExplicit("E" . ($row + 1), $partInfo["desc2"])
            ->setCellValueExplicit("E" . ($row + 2), "初始内库库存: " . $partInfo["innerStocks"][$orgDate])
            ->setCellValueExplicit("E" . ($row + 3), "初始外库库存: " . $partInfo["outerStocks"][$orgDate])
            ->setCellValueExplicit("E" . ($row + 4), "包装量：" . $partInfo["ordMin"])
            ->setCellValueExplicit("E" . ($row + 5), "前日需求量：" . $partInfo["dmds"][$orgDate])
    
            ->setCellValueExplicit("F" . $row, "需求")
            ->setCellValueExplicit("F" . ($row + 1), "计划")
            ->setCellValueExplicit("F" . ($row + 2), "内库")
            ->setCellValueExplicit("F" . ($row + 3), "外库 ")
            ->setCellValueExplicit("F" . ($row + 4), "总结余")
            ->setCellValueExplicit("F" . ($row + 5), "累积量")
    
            ;
    
    
    
            // 逐条日期写入数量信息
            $prefix = '';
            $j = 'G';
            foreach ($activeDates as $date) {
                $objActSheet
                ->setCellValueExplicit($prefix . $j . $row,       floatval($partInfo["dmds"][$date]))
                ->setCellValueExplicit($prefix . $j . ($row + 1), floatval($partInfo["prods"][$date]))
                ->setCellValueExplicit($prefix . $j . ($row + 2), floatval($partInfo["innerStocks"][$date]))
                ->setCellValueExplicit($prefix . $j . ($row + 3), floatval($partInfo["outerStocks"][$date]))
                ->setCellValueExplicit($prefix . $j . ($row + 4), floatval($partInfo["stocks"][$date]))
                ->setCellValueExplicit($prefix . $j . ($row + 5), floatval($partInfo["consectAccuDmds"][$date]))
                ;
    
                $j = chr(ord($j) + 1);
                if ($j > "Z") {
                    $j =  'A';
                    if ($prefix == '') {
                        $prefix =  'A';
                    } else {
                        $prefix = chr(ord($prefix) + 1);
                    }
                }
            }
    
            $row = $row + 7;
        }
    
        $totDmds = $this->getTotalDayDmds();
        $orgDate = $this->getOrgDate();
        $objActSheet
        ->mergeCells("A$row:D$row")
        ->setCellValue("A$row", "总需求量")
        ->setCellValue("E$row", "前日需求量")
        ->setCellValue("F$row", $totDmds[$this->getOrgDate()]);
        $prefix = '';
        $j = 'G';
        unset($totDmds[$orgDate]);
        foreach ($totDmds as $date => $totDmd) {
            $objActSheet->setCellValueExplicit($prefix . $j . $row, $totDmd);
    
            $j = chr(ord($j) + 1);
            if ($j > "Z") {
                $j =  'A';
                if ($prefix == '') {
                    $prefix =  'A';
                } else {
                    $prefix = chr(ord($prefix) + 1);
                }
            }
        }
        $row++;
    
        $totProds = $this->getTotalDayProds();
        $objActSheet
        ->mergeCells("A$row:F$row")
        ->setCellValue("A$row", "总生产数");
        $prefix = '';
        $j = 'G';
        foreach ($totProds as $date => $totProd) {
            $objActSheet->setCellValueExplicit($prefix . $j . $row, $totProd);
    
            $j = chr(ord($j) + 1);
            if ($j > "Z") {
                $j =  'A';
                if ($prefix == '') {
                    $prefix =  'A';
                } else {
                    $prefix = chr(ord($prefix) + 1);
                }
            }
        }
    
    
        $today = date("Y-m-d");
        $filename = "$this->_proj assy balance table($today).xls";
        $fileName = iconv("utf-8", "gb2312", $fileName);
        $objPHPExcel->setActiveSheetIndex(0);
         
    
        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
    
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
    }
    
    
    
}