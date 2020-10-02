<?php


namespace Home\Controller;
use Think\Controller;
use Think\Model;

/**
 * 物料MRP控制器
 * @author wz
 *
 */
class ProWeekMrpController extends BaseWeekMrpController
{

    protected function getMrpModel()
    {
        if (empty($this->_mrpModel)) {
            $this->_mrpModel = M("proweekmrp");
        }
    
        return $this->_mrpModel;
    }
    
    public function _initialize ()
    {
        parent::_initialize();
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
        
        // 只显示采购员自己关联的零件的采购运算
        if (self::getUserType() == 'P') {
            $map["comp_buyer"] = self::getCurrentBuyer();
        }
        
        if (!isset($_REQUEST["par_id"])) {
            $par_ids = [];
        } else {
            $par_ids = array_values(array_filter(array_map("intval", explode("_", $_REQUEST["par_id"]))));
        }
        
        if (count($par_ids) == 1) {
            $map["par_id"] = $par_ids[0];
        } else if (count($par_ids) > 1) {
            $map["par_id"] = ['IN', $par_ids];
        }
        
        if (isset($_REQUEST["par_f"]) && !empty($_REQUEST["par_f"]) && isset($_REQUEST["par_v"]) && !empty($_REQUEST["par_v"])) {
            $field = trim($_REQUEST["par_f"]);
            $val = trim($_REQUEST["par_v"]);
            $map[$field] = ["like", "$val%"];
        
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
    
    
    /**
     * 按照供应商代码来进行分页显示
     * @see \Home\Controller\CommonController::index()
     */
    public function index ($asChecker = false)
    {
        $asChecker = filter_var($asChecker, FILTER_VALIDATE_BOOLEAN);
        
        $model = $this->getMrpModel();
        $map = $this->_search();
        if ($asChecker) {
            $map["tran_ispass"] = '1';
            $map["comp_iswmrp"] = '0';
        } else {
            //$map["tran_ispass"] = ["neq", '2'];
        }
        
        if (isset($_REQUEST['orderField'])) {
            $orderField = $_REQUEST['orderField'];
        }
        if ($orderField == '') {
            //$orderField = $model->getPk();
            // 默认按照未审批、已提交、已审核的状态进行排序，方便申购员查询和提交时按顺序操作。
            // 审核员仍然只会看到已提交的数据。
            $orderField = 'tran_ispass, comp_vend';
        }
        if (isset($_REQUEST['orderDirection'])) {
            $sort = $_REQUEST['orderDirection'];
        }
        if ($sort == '') {
            $sort = 'asc';
        }
        
        if (isset($_REQUEST['pageCurrent'])) {
            $pageCurrent = $_REQUEST['pageCurrent'];
        }
        if ($pageCurrent == '') {
            $pageCurrent = 1;
        }
        
        $model->startTrans();
        // 获取匹配生产计划的供应商
        $vends = $model->distinct(true)->field("comp_vend")->order("$orderField $sort")->where($map)->getField("comp_vend", true);

        $count = count($vends);
 
        if ($count > 0) {
            // 获取所有可用日生产计划日期数组
            $weeksTh = $this->getMrpWeeks();
 
            $vend = $vends[$pageCurrent - 1];
            
            // 如果当前页供应商需要进行mrp，执行。
            if ($this->isCompsWeekMrpOfVend($vend)) {
                $this->doCompsWeekMrpOfVend($vend);
            }

            
            // 不要直接使用 _search()返回的可能包含多余筛选的条件，直接使用基础条件。
            $map = $this->getMrpConds();
            $map["comp_vend"] = $vend;
            // 获取用于mrp计算的最近版本（尚未审核或最新已审核）数据 
            $activeNbr = $this->getActiveNbrOfVend($vend);
            $map["tran_nbr"] = $activeNbr ? $activeNbr : ["exp", "is null"];
            
            
            // 单页显示结果按照零件号排序
            $result = $model->where($map)->order("comp_part")->select();
 
             
            $parts = self::convertToCompsInfoFromDbResult($result);
            $parts =  self::accumulateCompsQtys($parts); 
            $parts = self::calculateCompsStock($parts);
            //$parts = self::filterNoneOrderedParts($parts);
            
            $activePassState = reset($parts)["tran_ispass"];
   
            
            $model->commit();
            
            $numPerPage = count($parts);
        
        
            $this->assign("startWeek", min($weeksTh));
            $this->assign("weeksTh", $weeksTh);
            $this->assign("parts", $parts);
            $this->assign("vend", $vend);
            $this->assign("activeNbr", $activeNbr);
            $this->assign("activePassState", $activePassState);
        
        }
        $this->assign('totalCount', $count);
        $this->assign('currentPage', ! empty($_REQUEST[C('VAR_PAGE')]) ? $_REQUEST[C('VAR_PAGE')] : 1);
        $this->assign('numPerPage', $numPerPage);
        $this->assign('pageCurrent', $pageCurrent);
        cookie('_currentUrl_', __SELF__);
        $this->assign("asChecker", $asChecker);
        

        if ($asChecker) {
            switch($_REQUEST["site"]) {
                case 1000:
                    $this->display("proWeekMrp:index-nb");
                    break;
                case 6000:
                    $this->display("proWeekMrp:index-cq");
                    break;
            }
        } else {
            switch($_REQUEST["site"]) {
                case 1000:
                    $this->display("index-nb");
                    break;
                case 6000:
                    $this->display("index-cq");
                    break;
            }
        }
    }
    
    static protected function calculateCompsStock ($compsInfo)
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
            $options["orderWeekMap"] = $compInfo["shop_qtys"];
    
            $oac = new \Home\Model\OrderWeekAmountCalculatorModel($options);
            $oac->calculateStockData();
            $compInfo["stock_qtys"] = $oac->getStockWeekMap($invalidStockStartWeek);
            $compInfo["invalid_stock_start_week"] = $invalidStockStartWeek;
    
            if ($invalidStockStartWeek) {
                //throw new \Exception("the provided ord qty would break the safty stock!");
            }
            
        }
    
        return $compsInfo;
    }
    
    static protected function filterNoneOrderedParts ($compsInfo)
    {
        foreach ($compsInfo as $key => &$compInfo) {
            // filter none-ordered vend-part record
            if (array_filter($compInfo["shop_qtys"]) == [] && array_filter($compInfo["tran_qtys"]) == []) {
                unset($compsInfo[$key]);
            }
        }
    
    
        return $compsInfo;
    }
    
    
    /**
     * 提交时执行
     * @throws \Exception
     */
    public function submitVendOrder()
    {
        if (!IS_AJAX) {
            throw new \Exception("invalid post data from none-ajax request");
        }
        
        $err = false;
        $msg = '';
        $curTime = date("Y-m-d H:i:s");
        if (isset($_POST["vend"])) {
            $tranModel = $this->getTranModel();
            $tranModel->startTrans();
           
            
            if (isset($_POST["orderQty"])) {
                foreach ($_POST["orderQty"] as $key => $weekStrMap) {
                    list($vd_addr, $comp_part, $comp_site) = array_map("addslashes", explode("-", $key));
                    if (empty($vd_addr) || empty($comp_part) || empty($comp_site)) {
                        continue;
                    }
 
                    // 获取用于mrp计算的最近版本（尚未审核或最新已审核）数据,并保存修改的mrp数据。
                    $activeNbr = $this->getActiveNbrOfVend($vd_addr);
                    foreach ($weekStrMap as $weekStr => $ord_qty) {
                        $date = self::getStartDateOfWeekStr($weekStr);
                        $where = [];
                        $where['tran_part']    =    ':tran_part';
                        $where['tran_site']    =    ':tran_site';
                        $where['tran_vend']    =    ':tran_vend';
                        $where['tran_date']    =    ':tran_date';
                        $bind = [];
                        $bind[':tran_part']  =    array($comp_part,\PDO::PARAM_STR);
                        $bind[':tran_site']  =    array($comp_site,\PDO::PARAM_STR);
                        $bind[':tran_vend']  =    array($vd_addr,\PDO::PARAM_STR);
                        $bind[':tran_date']  =    array($date,\PDO::PARAM_STR);
                
//                         if ($activeNbr) {
//                             $where['tran_nbr']     =    ':tran_nbr';
//                             $bind[':tran_nbr']     =    [$activeNbr,\PDO::PARAM_STR];
//                         } else {
//                             $where["tran_nbr"] = ["exp", "is null"];
//                         }
                        
                        $where["tran_nbr"] = ["exp", "is null"];
                        
                        // 提交数据时数据库中必然已存在对应的活动版本数据记录
                        if ($tranModel->where($where)->bind($bind)->count()) {
                            $tranModel->tran_name = ':tran_name';
                            $tranModel->tran_mtime = ':tran_mtime';
                            $tranModel->tran_ord_qty = ':tran_ord_qty';
                            $tranModel->tran_ispass = ':tran_ispass';
                
                            $bind[':tran_name']  =    array(self::getUserName(),\PDO::PARAM_STR);
                            $bind[':tran_mtime']  =    array($curTime,\PDO::PARAM_STR);
                            $bind[':tran_ord_qty']  =    array($ord_qty,\PDO::PARAM_STR);
                            $tran_ispass = 1;
                            $bind[':tran_ispass']  =    array($tran_ispass,\PDO::PARAM_STR);
                            $tranModel->where($where)->bind($bind)->save();
                        } else {
                            throw new \Exception("no original unnbr data exists, update is forbidden");
                        }
                    }
                }
            }

            
            // 将当前vend页的所有周送货子部件的活动版本ispass状态置为1.
            $vend = trim($_POST["vend"]);
            $comps = $this->getCompsOfVend($vend);
            
 
            $activeNbr = $this->getActiveNbrOfVend($vend);
            $map = [];
            $map["tran_part"] = ["in", $comps];
            $map["tran_vend"] = $vend;
            $map["tran_nbr"] = $activeNbr ? $activeNbr : ["exp", "is null"];
            $tranModel->tran_ispass = 1;
            $tranModel->where($map)->save();
            
            $tranModel->commit();
            $msg = "已提交供应商: $vend 的周采购数据";
        } else {
            $err = true;
            $msg = '非法请求：未提交供应商周采购数据';
        }
    
    
        $data = new \stdClass();
        $data->statusCode = $err ? 300 : 200;
        $data->message = $msg ?  $msg : "供应商周采购数量确认成功";
        $this->ajaxReturn($data);

    }
    

    
  
    
    public function exportBalanceExcel()
    {
        set_time_limit(500);
        $data = [];
        $model = $this->getMrpModel();
    
        $map = self::$baseConds;
        $map = $this->_search();
    
        $vendAddrsResult = $model->distinct(true)->field("comp_vend")->order("id")->where($map)->select();
        foreach ($vendAddrsResult as $row) {
            $vends[] = $row["comp_vend"];
        }
        sort($vends);
        $vendsData = [];
        $weeks = $this->getMrpWeeks();
        foreach ($vends as $vend) {
            if ($this->isCompsWeekMrpOfVend($vend)) {
                $this->doCompsWeekMrpOfVend($vend);
            }
            
            $map = $this->getMrpConds();
            $map["comp_vend"] = $vend;
            // 获取用于mrp计算的最近版本（尚未审核或最新已审核）数据
            $activeNbr = $this->getActiveNbrOfVend($vend);
            $map["tran_nbr"] = $activeNbr ? $activeNbr : ["exp", "is null"];
            
            
            $result = $model->where($map)->order("comp_part")->select();
            
            $dates = [];
//             $items = self::convertToCompsInfoFromDbResult($result);
//             $items =  self::accumulateCompsQtys($items);
//             $items = self::calculateCompsMrp($items);
            
            $items = self::convertToCompsInfoFromDbResult($result);
            $items =  self::accumulateCompsQtys($items);
            $items = self::calculateCompsStock($items);
            //$items = self::filterNoneOrderedParts($items);
            
            
            $vendsData[$vend] = $items;
        }
       
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
        foreach ($vendsData as $vend => $items) {
            $objPHPExcel->setActiveSheetIndex($sheetIndex);
            $objActSheet = $objPHPExcel->getActiveSheet();
    
            for ($colOrd = ord('A'); $colOrd < ord('N'); $colOrd++) {
                $objActSheet->getColumnDimension(chr($colOrd))->setAutoSize(true);
            }
    
            $vendName = reset($items)["vd_sort"];
            $objPHPExcel->getActiveSheet()->setTitle($vend);
    
            // display the logo
            $logoStartCell = 'A1';
            $logoEndCell = chr(ord('A') + count($headers) - 1) . '1';
            if ($logoEndCell < 'O1') {
                $logoEndCell = 'O1';
            }
            
            $objActSheet
            ->mergeCells("$logoStartCell:$logoEndCell")
            ->setCellValue($logoStartCell, "$vendName-周平衡表");
            $logoCellStyle = $objActSheet->getStyle($logoStartCell);
            $logoCellStyle->getFont()->setName("微软雅黑")->setBold(true)->setSize(30)
            ->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_BLUE);
            $logoCellStyle->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    
    
            // display the balance title
            $key = ord("A");
            foreach ($headers as $v) {
                $colum = chr($key);
                $objActSheet->setCellValue($colum . '2', $v);
                $key += 1;
            }
    
    
            $column = 3;
            $objActSheet = $objPHPExcel->getActiveSheet();
            foreach ($items as $key => $part) { // 行写入
                // 用五行来写入一个零件
    
                $objActSheet
                //->mergeCells("A$column:A" . ($column + 3))
                ->setCellValueExplicit("A$column", $part["comp_part"])
                ->setCellValueExplicit("A" . ($column + 1), $part["comp_part"])
                ->setCellValueExplicit("A" . ($column + 2), $part["comp_part"])
                ->setCellValueExplicit("A" . ($column + 3), $part["comp_part"])
                ;
    
                // 写入基础数量信息
                $objActSheet
                ->setCellValueExplicit("B$column", $part["comp_desc1"])
                ->setCellValueExplicit("B" . ($column + 1), "收容: " . floatval($part["comp_ord_mult"]))
                ->setCellValueExplicit("B" . ($column + 2), "安全: " . floatval($part["comp_rop"]))
                ->setCellValueExplicit("B" . ($column + 3), "在库: " . floatval($part["in_qty_oh"]))
                ;
    
                $objActSheet
                ->setCellValueExplicit("C$column", "需求")
                ->setCellValueExplicit("C" . ($column + 1), "采购")
                ->setCellValueExplicit("C" . ($column + 2), "在途")
                ->setCellValueExplicit("C" . ($column + 3), "结余")
                ;
    
                // 逐条日期写入数量信息
                $j = 'D';
                foreach ($weeks as $week) {
                    $objActSheet
                    ->setCellValueExplicit("$j$column", floatval($part["dmnd_qtys"][$week]))
                    ->setCellValueExplicit($j . ($column + 1), floatval($part["shop_qtys"][$week]))
                    ->setCellValueExplicit($j . ($column + 2), floatval($part["tran_qtys"][$week]))
                    ->setCellValueExplicit($j . ($column + 3), floatval($part["stock_qtys"][$week]))
                    ;
                    $j = chr(ord($j) + 1);
                }
    
                $column = $column + 4;
            }
    
    
    
            $sheetIndex++;
            $objPHPExcel->createSheet();
    
        }
    
    
        
    
        $today = date("Y_m_d");
        $ptpBuyer = self::getCurrentBuyer();
        if ($ptpBuyer) {
            $filename = "周采购平衡表-$ptpBuyer-$today.xls";
        } else {
            $filename = "周采购平衡表-全部-$today.xls";
        }
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