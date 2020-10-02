<?php

 

namespace Home\Controller;
use Think\Controller;

class ProDayVendController extends ProDayMrpController{

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
    
        $map["tran_date"] = ["EGT", $this->getInitStockDate()];
    
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
        
        $uitems = $mrpModel->lock(true)->distinct(true)->where($map)->order("$orderField $sort")
        ->field("vd_addr, vd_sort, comp_buyer, tran_nbr")->select();
        
        $items = [];
        foreach ($uitems as $key => $item) {
            $uuid = "$item[vd_addr]-$item[tran_nbr]";
            if (!isset($items[$uuid]) || $item["comp_buyer"] != 'P8999') {
                $items[$uuid] = $item;
            }
        }

        $count = count($items);
        
        
        $this->assign("vends", $items);
        
        $this->assign('totalCount', $count);
        $this->assign('currentPage', ! empty($_REQUEST[C('VAR_PAGE')]) ? $_REQUEST[C('VAR_PAGE')] : 1);
        $this->assign('pageCurrent', 1);
        cookie('_currentUrl_', __SELF__);
 
        
        
        switch($_REQUEST["site"]) {
            case 1000:
                $this->display("index-nb");
                break;
            case 6000:
                $this->display("index-cq");
                break;
        }
    }
    
    
    static protected function convertToNbrVendInfoFromDbResult($result, &$dates = [])
    {
        $dates = [];
        foreach ($result as $row) {
            $date = $row["tran_date"] ;
            if (!isset($dates[$date])) {
                $dates[$date] = $date;
            }
        }
        sort($dates);
        $dateWeekdayMap =  self::getDateWeekdayMap($dates);
        
        $nbrVendsInfo = [];
        foreach ($result as $row) {
            $uid =  $row["comp_part"] . "-" . $row["comp_vend"] . "-" . $row["tran_nbr"]. "-" . $row["comp_site"];
            if (!isset($nbrVendsInfo[$uid])) {
                $nbrVendsInfo[$uid] = $row;
                $nbrVendsInfo[$uid]["tran_ispass"] = intval($row["tran_ispass"]);
                //$nbrVendsInfo[$uid]["fday_map"] = self::calculateFirstFdayMap(reset($dates), end($dates), $row["vd_fday"]);
            }
        
            if (!isset($nbrVendsInfo[$uid]["tran_qtys"][$row["tran_date"]])) {
                $nbrVendsInfo[$uid]["tran_qtys"][$row["tran_date"]] = floatval($row["tran_qty"]);
            }
        }
        
        // 过滤任何日期都无到货需求的物料
        foreach ($nbrVendsInfo as $key => $nbrVendInfo) {
            $hasQty = false;
            foreach ($nbrVendInfo['tran_qtys'] as $date => $qty) {
                if ($qty != 0) {
                    $hasQty = true;
                    break;
                }
            }
            
            if (!$hasQty) {
                unset($nbrVendsInfo[$key]);
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
        foreach ($vdAddrNbrMap as $vend => $nbrs) {
            $map["comp_vend"] = $vend;
            foreach ($nbrs as $nbr) {
                $map["tran_nbr"] =  $nbr;
                $result = $model->where($map)->select();
    
                $dates = [];
                $nbrVendInfo = self::convertToNbrVendInfoFromDbResult($result, $dates);
                $vdAddrNbrData[$vend][$nbr] = $nbrVendInfo;
            }
        }
        
        // write csv title line
        $headers = [
                "序号",
                "物料代码",
                "物料名称",
                "规格型号",
                "SNP",
        ];
        $headers = array_merge($headers, $dates);
    
    
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
        $headers = array_merge($headers, $dates);
    
        $sheetIndex = 0;
        foreach ($vdAddrNbrData as $vend => $nbrsInfo) {
            foreach ($nbrsInfo as $nbr => $nbrInfo) {
                $objPHPExcel->setActiveSheetIndex($sheetIndex);
                $objActSheet = $objPHPExcel->getActiveSheet();
                
                for ($colOrd = ord('A'); $colOrd < ord('Z'); $colOrd++) {
                    $objActSheet->getColumnDimension(chr($colOrd))->setAutoSize(true);
                }
                
                
                
                $vendName = reset($nbrInfo)["vd_sort"];
//                 $vendFdayMap = reset($nbrInfo)['fday_map'];
//                 $vendDates = [];
//                 foreach ($dates as $date) {
//                     if ($vendFdayMap[$date]) {
//                         $vendDates[] = $date;
//                     }
//                 }
                $vendDates = $dates;

                $objPHPExcel->getActiveSheet()->setTitle("$vend-$nbr");
                
                // write csv title line
                $headers = [
                        "序号",
                        "物料代码",
                        "物料名称",
                        "规格型号",
                        "包装量",
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
                $prefix = '';
                $j = "A";
                $colChars = [];
                foreach ($headers as $header) {
                    $objActSheet->setCellValue($prefix . $j . '4', $header);
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
                
                
                // display the company header
                $logoStartCell = 'A1';
                $logoEndCell = end($colChars) . '1';
                $objActSheet
                ->mergeCells("$logoStartCell:$logoEndCell")
                ->setCellValue($logoStartCell, "宁波胜维德赫华翔汽车镜有限公司采购订单");
                $logoCellStyle = $objActSheet->getStyle($logoStartCell);
                $logoCellStyle->getFont()->setName("微软雅黑")->setBold(true)->setSize(30)
                ->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_BLUE);
                $logoCellStyle->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                
                // display data list
                $column = 5;
                foreach ($data as $key => $rows) { // 行写入
                    $prefix = '';
                    $j = 'A';
                    foreach ($rows as $keyName => $value) { // 列写入
                        $objActSheet->setCellValueExplicit($prefix . $j . $column, $value);

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
                    
                    $column ++;
                }
                
                
                
                $sheetIndex++;
                $objPHPExcel->createSheet();
            }
            
 
    
        }
    
    
    
        $filename = "vendOrds.xls";
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