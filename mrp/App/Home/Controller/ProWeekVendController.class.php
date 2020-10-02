<?php

 

namespace Home\Controller;
use Think\Controller;

class ProWeekVendController extends BaseWeekMrpController{

   public function _initialize() {
        parent::_initialize();
    }
	
    protected function getMrpModel()
    {
        if (empty($this->_mrpModel)) {
            $this->_mrpModel = M("provend");
        }
    
        return $this->_mrpModel;
    }
    
    protected function getMrpConds ()
    {
        $map = self::$baseConds;
        
        $weekStartDate = reset(self::getWeekPeriodDatesMapByWeekStrs($this->getMrpWeeks()))[0];
    
        $map["tran_date"] = ["EGT", $weekStartDate];
    
        return $map;
    }
    
    protected function _search ()
    {
        $map = $this->getMrpConds();
        switch($_REQUEST["site"]) {
            case 1000:
                $map["comp_site"] = '1000';
                break;
            case 6000:
                $map["comp_site"] = '6000';
                break;
        }
        
        $map["tran_ispass"] = '2';
    
        // 只显示采购员自己关联的零件的采购运算
        if (self::getUserType() == 'P') {
            $map["comp_buyer"] = self::getCurrentBuyer();
        }
        
    
    
        if (isset($_REQUEST["conds"])) {
            foreach ($_REQUEST["conds"] as $field => $val) {
                $val = trim($val);
                $map[$field] = ["like", "$val%"];
    
            }
        } else if (isset($_REQUEST["f"]) && !empty($_REQUEST["f"]) && isset($_REQUEST["v"]) && !empty($_REQUEST["v"])) {
            $field = trim($_REQUEST["f"]);
            $val = trim($_REQUEST["v"]);
            $map[$field] = ["like", "$val%"];
    
        }
    
        return $map;
    }
    

    public function index ()
    {
        $mrpModel = $this->getMrpModel();
        $map = $this->_search();
        
        if (isset($_REQUEST['orderField'])) {
            $orderField = $_REQUEST['orderField'];
        }
        if ($orderField == '') {
            //$orderField = $mrpModel->getPk();
            $orderField = 'id, tran_nbr';
        }
        if (isset($_REQUEST['orderDirection'])) {
            $sort = $_REQUEST['orderDirection'];
        }
        if ($sort == '') {
            $sort = 'asc';
        }
        
        $items = $mrpModel->lock(true)->distinct(true)->where($map)->order("$orderField $sort")
        ->field("vd_addr, vd_sort, comp_buyer, tran_nbr")->select();

        $count = count($items);
        
        
        $this->assign("vends", $items);
        
        $this->assign('totalCount', $count);
        $this->assign('currentPage', ! empty($_REQUEST[C('VAR_PAGE')]) ? $_REQUEST[C('VAR_PAGE')] : 1);
        $this->assign('pageCurrent', 1);
        cookie('_currentUrl_', __SELF__);
 
        
        $this->display();
    }
    

    

    
    
    static protected function convertToNbrVendInfoFromDbResult($result, $weeks)
    {
        
        $wdMap = self::getWeekPeriodDatesMapByWeekStrs($weeks);
        $week_day_qty_empty_arr = array_flip($weeks);
        array_walk($week_day_qty_empty_arr, function(&$val) {
            $val = [];
        });
        
        $nbrVendsInfo = [];
        foreach ($result as $row) {
            $uid =  $row["comp_part"] . "-" . $row["comp_vend"] . "-" . $row["tran_nbr"]. "-" . $row["comp_site"];
            if (!isset($nbrVendsInfo[$uid])) {
                $nbrVendsInfo[$uid] = $row;
                $nbrVendsInfo[$uid]['tran_week_day_qtys'] = $week_day_qty_empty_arr;
            }
        
            if (!is_null($row["tran_date"]) && !isset($compsInfo[$uid]["tran_week_day_qtys"][$row["wrps_week"]][$row["tran_date"]])) {
                if ($row["tran_date"] >= $wdMap[$row["wrps_week"]][0]  && $row["tran_date"] <= $wdMap[$row["wrps_week"]][1] ) {
                    $compsInfo[$uid]["tran_week_day_qtys"][$row["wrps_week"]][$row["tran_date"]] = floatval($row["tran_qty"]);
                }
            }
            
            
            if (!isset($nbrVendsInfo[$uid]["tran_qtys"][$row["tran_date"]])) {
                $nbrVendsInfo[$uid]["tran_qtys"][$row["tran_date"]] = floatval($row["tran_qty"]);
            }
        }
        
        return $nbrVendsInfo;
    }
    
    public function exportNbrVendsOrderExcel()
    {
        set_time_limit(500);
        $vdNbrs = explode(",", $_REQUEST["vdNbrs"]);
        $vdAddrNbrMap = [];
        foreach ($vdNbrs as $item) {
            if (preg_match('/(\d[\d\.]+)-(\d{10})/', $item, $match)) {
                $vdAddrNbrMap[$match[1]][] = $match[2];
            }
        }
        
    
        $data = [];
        $model = $this->getMrpModel();
         
        $vdAddrNbrData = [];
        //$map = $this->getMrpConds();
        $map = $this->_search();
        
        $weeks = $this->getMrpWeeks();
        foreach ($vdAddrNbrMap as $vend => $nbrs) {
            $map["comp_vend"] = $vend;
            foreach ($nbrs as $nbr) {
                $map["tran_nbr"] =  $nbr;
                $result = $model->where($map)->select();
    
                $nbrVendInfo = self::convertToNbrVendInfoFromDbResult($result, $weeks);
                $vdAddrNbrData[$vend][$nbr] = $nbrVendInfo;
            }
        }
        return;
        // write csv title line
        $headers = [
                "序号",
                "物料代码",
                "物料名称",
                "规格型号",
                "SNP",
        ];
        $headers = array_merge($headers, $weeks);
    
    
        import("Org.Util.PHPExcel");
        import("Org.Util.PHPExcel.Writer.Excel5");
        import("Org.Util.PHPExcel.IOFactory.php");
        $objPHPExcel = new \PHPExcel();
        $objProps = $objPHPExcel->getProperties();
    
        $headers = [
                "零件",
                "描述1",
                "类型",
        ];
        $headers = array_merge($headers, $weeks);
    
        $sheetIndex = 0;
        foreach ($vdAddrNbrData as $vend => $nbrsInfo) {
            foreach ($nbrsInfo as $nbr => $nbrInfo) {
                $objPHPExcel->setActiveSheetIndex($sheetIndex);
                $objActSheet = $objPHPExcel->getActiveSheet();
                
                for ($colOrd = ord('A'); $colOrd < ord('N'); $colOrd++) {
                    $objActSheet->getColumnDimension(chr($colOrd))->setAutoSize(true);
                }
                
                
                
                $vendName = reset($nbrInfo)["vd_sort"];
                $vendFdayMap = reset($nbrInfo)['fday_map'];
                $vendDates = [];
                foreach ($dates as $date) {
                    if ($vendFdayMap[$date]) {
                        $vendDates[] = $date;
                    }
                }

                $objPHPExcel->getActiveSheet()->setTitle("$vend-$nbr");
                
                // write csv title line
                $headers = [
                        "序号",
                        "物料代码",
                        "物料名称",
                        "规格型号",
                        "SNP",
                ];
                $headers = array_merge($headers, $vendDates);
                
                $i = 0;
                $data = [];
                foreach ($nbrInfo as $comp) {
                    ksort($comp['tran_qtys']);
                    $item = [
                            ++$i,
                            $comp["comp_part"],
                            $comp["comp_desc1"],
                            $comp["comp_desc2"],
                            floatval($comp["comp_ord_mult"]),
                    ];
                
                    foreach ($vendDates as $date) {
                        $item[] = $comp['tran_qtys'][$date] ? $comp['tran_qtys'][$date] : 0;
                    }
                    $data[] = $item;
                }
 
                
                // display the company header
                $logoStartCell = 'A1';
                //$logoEndCell = chr(ord('A') + count($headers) - 1) . '1';
                $logoEndCell = 'M1';
                $objActSheet
                ->mergeCells("$logoStartCell:$logoEndCell")
                ->setCellValue($logoStartCell, "宁波胜维德赫华翔汽车镜有限公司采购订单");
                $logoCellStyle = $objActSheet->getStyle($logoStartCell);
                $logoCellStyle->getFont()->setName("微软雅黑")->setBold(true)->setSize(30)
                ->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_BLUE);
                $logoCellStyle->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                
                // use two rows to display vend info
                $firstItem = reset($nbrInfo);
                $vdBuyer = $firstItem["comp_buyer"] ? $firstItem["comp_buyer"] : "无数据";
                $objActSheet
                ->mergeCells("A2:C2")
                ->setCellValue("A2", "日期：" . date("Y-m-d"))
                ->mergeCells("D2:K2")
                ->setCellValue("D2", "供应商联系人：" . $vdBuyer)
                ->mergeCells("L2:N2")
                ->setCellValue("L2", "供应商代码：" . $vend);
                $vdName = $firstItem["vd_sort"];
                $vdSort = $firstItem["vd_sort"] ? $firstItem["vd_sort"] : "无数据";
                $objActSheet
                ->mergeCells("A3:C3")
                ->setCellValue("A3", "供应商：" . $vdName)
                ->mergeCells("D3:K3")
                ->setCellValue("D3", "供应商地址：" . $vdSort)
                ->mergeCells("L3:N3")
                //->setCellValue("L3", "采购订单号：" . "")
                ;
                
                // display column header
                $key = ord("A");
                foreach ($headers as $v) {
                    $colum = chr($key);
                    $objActSheet->setCellValue($colum . '4', $v);
                    $key += 1;
                }
                
                // display data list
                $column = 5;
                $objActSheet = $objPHPExcel->getActiveSheet();
                foreach ($data as $key => $rows) { // 行写入
                    $span = ord("A");
                    foreach ($rows as $keyName => $value) { // 列写入
                        $j = chr($span);
                        $objActSheet->setCellValueExplicit($j . $column, $value);
                        $span ++;
                    }
                    $column ++;
                }
                
                
                
                $sheetIndex++;
                $objPHPExcel->createSheet();
            }
            
 
    
        }
    
    
    
        $filename = "供应商采购表.xls";
        $fileName = iconv("utf-8", "gb2312", $fileName);
    
        $objPHPExcel->setActiveSheetIndex(0);
        ob_end_clean();
      
     
        
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
    
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
    }
    
    
 
    
    
    
    public function test()
    {
        echo self::getCurrentWeekStr();
    }
}