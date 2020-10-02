<?php
namespace Home\Controller;
use Think\Controller;
use Think\Model;
import("Date.DateHelper");

/**
 * 生产计划控制器
 * @author wz
 *
 */
class ProplanController extends BaseMrpController
{
    protected $_allowedCondFields = [
            "ptp_site", "drps_line", "ptp_part", "ptp_cpart" , "ptp_desc1", "ptp_desc2", "ptp_promo", "ptp_peizhi", "ptp_buyer", "ptp_vend"  
    ];

    public function _initialize ()
    {
        parent::_initialize();
        $this->dbname = CONTROLLER_NAME;
    }
    

    
    protected function _search ()
    {

        $map = ["ptp_pm_code" => "L"];
        
        switch($_REQUEST["site"]) {
            case 1000:
                $map["ptp_site"] = '1000';
                break;
            case 6000:
                $map["ptp_site"] = '6000';
                break;
        }
        
        // 只显示计划员自己关联的父物料的生产计划
        if (self::getUserType() == 'M') {
            $map["ptp_buyer"] = self::getCurrentPlaner();
        }
        
        
        
        if (isset($_REQUEST["conds"])) {
            foreach ($_REQUEST["conds"] as $field => $val) {
                if (in_array(trim($field), $this->_allowedCondFields)) {
                    $val = trim($val);
                    $map[$field] = ["like", "$val%"];
                }
            
            }
        } else if (isset($_REQUEST["f"]) && !empty($_REQUEST["f"]) && isset($_REQUEST["v"]) && !empty($_REQUEST["v"])) {
            $field = trim($_REQUEST["f"]);
            if (in_array($field, $this->_allowedCondFields)) {
                $val = trim($_REQUEST["v"]);
                $map[$field] = ["like", "$val%"];
            }

        }

        return $map;

    }
    
    protected function getSiteCond()
    {
        if (isset($_REQUEST['site'])) {
            $site = trim($_REQUEST['site']);
        } else {
            $site = '';
        }
        
        return ['drps_site' => $site ];
    }
    
    public function getDates()
    {
        $dates = M("drps_mstr")->distinct(true)->field("drps_date")->where($this->getSiteCond())->order("drps_date")->getField("drps_date", true);
        return $dates;
    }
    
    /**
     * get formatted date headers map
     */
    public function getFormattedDatesMap ()
    {
        $map = [];
        if (isset($_REQUEST["site"])) {
            $map["drps_site"] = $_REQUEST["site"];
        }
        $result = M("drps_mstr")->distinct(true)->field("drps_date, drps_type")->where($map)->select();
        $map = [];
        foreach ($result as $row) {
            $date = $row["drps_date"];
            $formattedDHeader = self::getFormattedDate($date, $row["drps_type"]);
            $map[$date] = $formattedDHeader;
        }
        
        return $map;
    }
    
    
    public function lndDayPlanReport ($line = '', $date = '')
    {
        if (empty($date)) {
            $date = date("Y-m-d");
        }
        
        $this->assign("line", $line);
        $this->assign("date", $date);
        $this->display();
    }
    
    
    public function exportLndDayPlanReport ($line, $date)
    {
        set_time_limit(500);
        
        if (strtoupper($line) == "M00001" || strtoupper($line) == "M00061") {
            //$isMoldLine = true;
        }
        
        $allData = [];
        $model = M("proplan");
        $map = [
                "drps_line" => $line,
                "drps_date" => $date
        ];
        
        $result = $model->where($map)->order("ptp_part")->select();
        $plans = self::convertToPlansFromDbResult($result);
        
        foreach ($plans as $plan) {
            if ($plan["drps_qty"] != 0) {
                $allData[] = $plan;
            }
        }


        import("Org.Util.PHPExcel");
        import("Org.Util.PHPExcel.Writer.Excel5");
        import("Org.Util.PHPExcel.IOFactory.php");
        $objPHPExcel = new \PHPExcel();
        
        $headers = [
                "序号",
                "物料代码",
                "物料名称",
                "规格型号",
                "计划产量",
                "生产数",
                "合格数",
                "入库数",
                "不良数",
                "合格未入库数",
                "班次",
                "备注",
        ];
 
        if ($isMoldLine) {
            array_splice($headers, 5, 0, "机台");
       }
        $endCol = chr(ord('A') + count($headers) - 1);

        
        $objPHPExcel->setActiveSheetIndex(0);
        $objActSheet = $objPHPExcel->getActiveSheet();
        $objActSheet->setTitle("产线日计划下发");
        
        
 
        $objActSheet->getColumnDimension('A')->setWidth(10);
        $objActSheet->getColumnDimension('B')->setWidth(20);
        $objActSheet->getColumnDimension('C')->setWidth(30);
        $objActSheet->getColumnDimension('D')->setWidth(15);
        $objActSheet->getColumnDimension('E')->setWidth(15);
        $objActSheet->getColumnDimension('F')->setWidth(10);
        $objActSheet->getColumnDimension('G')->setWidth(10);
        $objActSheet->getColumnDimension('H')->setWidth(20);
        $objActSheet->getColumnDimension('I')->setWidth(10);
        $objActSheet->getColumnDimension('J')->setWidth(10);
        
 
        
        // display the logo
        $logoStartCell = 'A1';
        $logoEndCell = $endCol . '1';
        $objActSheet
        ->mergeCells("$logoStartCell:$logoEndCell")
        ->setCellValue($logoStartCell, "宁波胜维德赫华翔汽车零部件有限公司");
        $logoCellStyle = $objActSheet->getStyle($logoStartCell);
        $logoCellStyle->getFont()->setName("微软雅黑")->setBold(true)->setSize(30)
        ->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_BLUE);
        $logoCellStyle->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        
        $logoStartCell = 'A2';
        $logoEndCell = $endCol . '2';
        $objActSheet
        ->mergeCells("$logoStartCell:$logoEndCell")
        ->setCellValue($logoStartCell, "日生产计划表-$line");
        $logoCellStyle = $objActSheet->getStyle($logoStartCell);
        $logoCellStyle->getFont()->setName("微软雅黑")->setBold(true)->setSize(20);
        $logoCellStyle->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
        

 
        $j = "A";
        foreach ($headers as $header) {
            $objActSheet->setCellValue($j . '3', $header);
            $j = chr(ord($j) + 1);
        
        
        }


        
        $column = 4;
        $id = 1;
        foreach ($allData as $item) {
            $objActSheet
            ->setCellValueExplicit("A" . $column, $id)
            ->setCellValueExplicit("B" . $column, $item["ptp_part"])
            ->setCellValueExplicit("C" . $column, $item["ptp_desc1"])
            ->setCellValueExplicit("D" . $column, $item["ptp_desc2"])
            ->setCellValueExplicit("E" . $column, floatval($item["drps_qty"]));
            
            if ($isMoldLine) {
                $objActSheet->setCellValueExplicit("F" . $column, $item["drps_mach"]);
            } else {
                $objActSheet->setCellValueExplicit("F" . $column, "");
            }
            
            $objActSheet
            ->setCellValueExplicit("G" . $column, "")
            ->setCellValueExplicit("H" . $column, "")
            ->setCellValueExplicit("I" . $column, "")
            ->setCellValueExplicit("J" . $column, "")
            ->setCellValueExplicit("K" . $column, "")
            ->setCellValueExplicit("L" . $column, "")
            ;
            
            if ($isMoldLine) {
                $objActSheet->setCellValueExplicit("M" . $column, "");
            }  
            
            $id++;
            $column++;
        }
        
        
        $objActSheet
        ->setCellValue("A$column", "打印人")
        ->mergeCells("B$column:C$column")
        ->setCellValue("B$column", self::getUserName())
        ->setCellValue("J$column", "日期")
        ->mergeCells("K$column:$endCol$column")
        ->setCellValue("K$column", $date)
        ;
        
        

        $filename = "dailyPlan-$line-$date.xls";
        
        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
         
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
    }
    
    
    public function browse ($showSum = true)
    {
        $this->index($showSum, true);
    }
    
    /**
     * 展示数据
     * {@inheritDoc}
     * @see \Home\Controller\CommonController::index()
     */
    public function index ($showSum = true, $noedit = false)
    {
        $showSum = filter_var($showSum, FILTER_VALIDATE_BOOLEAN);
        
        $model = M($this->dbname);
        $map = $this->_search();
        if (method_exists($this, '_filter')) {
            $this->_filter($map);
        }
        if (isset($_REQUEST['orderField'])) {
            $orderField = $_REQUEST['orderField'];
        }
        if (method_exists($this, '_befor_index')) {
            $this->_befor_index();
        }
        if ($orderField == '') {
            $orderField = $model->getPk();
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
            
        if (isset($_REQUEST['site'])) {
            $site = trim($_REQUEST['site']);
        } else {
            $site = '';
        }

            //$count = $model->where($map)->count("distinct id");
        $count = count($model->distinct(true)->field("id")->where($map)->select());


        // 获取指定地点的所有计划日期范围

        $dates = $this->getDates();
        // 获取格式化的日期标题
        $dHeadersMap = self::getFormattedDatesMap();

        
        if ($count > 0) {

            $numPerPage = 20;
            
            $dbprefix = C('DB_PREFIX');
            $proplandb = $dbprefix . "proplan";
            $interPtpIdTable = $model->fetchSql(true)->distinct(true)->field("id as inter_ptp_id")
            ->where($map)->order("$orderField $sort")->page($pageCurrent, $numPerPage)->select();;


            
            $result = $model->join("right join ($interPtpIdTable) as interids on $proplandb.id=interids.inter_ptp_id")
            ->order("$orderField $sort")
            ->select();  


        }
        $this->assign('totalCount', $count); // 数据总数
        $this->assign('currentPage',
                ! empty($_REQUEST[C('VAR_PAGE')]) ? $_REQUEST[C('VAR_PAGE')] : 1); // 当前的页数，默认为1
        $this->assign('numPerPage', $numPerPage); // 每页显示多少条
        $this->assign('pageCurrent', $pageCurrent);
        cookie('_currentUrl_', __SELF__);

        
        $parts = self::convertToPlansFromDbResult($result);


        if ($showSum && isset($map["ptp_buyer"])) {
            // get total sum
            $result = $model->where($map)->select();
            $totalParts = self::convertToPlansFromDbResult($result);

            $datesSum = [];  
            foreach ($totalParts as $key => $part) {
                if (isset($part["drps"])) {
                    foreach ($dates as $d) {
                        $datesSum[$d] += floatval($part["drps"][$d]["drps_qty"]);
                    }
            
                }
            }

            ksort($datesSum);
          
            $this->assign("datesSum", $datesSum);
        }
       
        
        $this->assign("startDate", min($dates));
        $this->assign("dates", $dates);
        $this->assign("dHeadersMap", $dHeadersMap);
        $this->assign("parts", $parts);
        $this->assign("condFields", $this->_allowedCondFields); 
        $this->assign("utype", $this->getUserType());

        if (!filter_var($noedit, FILTER_VALIDATE_BOOLEAN)) {
            switch($_REQUEST["site"]) {
                case 1000:
                    $this->display("index-nb");
                    break;
                case 6000:
                    $this->display("index-cq");
                    break;
            }
        } else {
            switch($_REQUEST["site"]) {
                case 1000:
                    $this->display("proplanview:index-nb");
                    break;
                case 6000:
                    $this->display("proplanview:index-cq");
                    break;
            }
        }

    }

    static function convertToPlansFromDbResult($result)
    {  
        $parts = [];
        foreach ($result as $item) {
            $uid = $item["ptp_part"] . $item["ptp_site"] . $item["drps_line"];
            if (!isset($parts[$uid])) {
                $parts[$uid] = $item;
            }
            if (!isset($parts[$uid]["drps"][$item["drps_date"]])) {
                $parts[$uid]["drps"][$item["drps_date"]] = [
                        'drps_qty' => floatval($item["drps_qty"]),
                        'drps_id' => $item["drps_id"],
                        'drps_ismrp' => (bool)$item["drps_ismrp"],
                        'drps_type' => $item["drps_type"]
                ];
            }
        
 
        }
 
         
        // sort by drp_date;
        foreach ($parts as &$rpart) {
            isset($rpart["drps"]) && ksort($rpart["drps"]);
        }
        
        return $parts;
    }
    
    
 
    
 
    /**
     * 根据指定的物料号，删除对应的所有生产计划
     * @throws \Exception
     */
    public function deleteRps ()
    {
        $ptp_ids = array_map("intval", explode(",", $_REQUEST["ptp_id"]));
        if (empty($ptp_ids)) {
            return;
        }
        
        $err = false;
        $msg = '';
        try {
            $ptp = D("PtpDet");
            $ptp->startTrans();
            
            $dbprefix = C('DB_PREFIX');
            $ptpdetdb = $dbprefix . "ptp_det";
            $drpsmstrdb = $dbprefix . "drps_mstr";
            $ptpCond["$ptpdetdb.id"] = array("in", $ptp_ids);
            
            
            $drpIds = $ptp
            ->lock(true)
            ->join("$drpsmstrdb ON $ptpdetdb.ptp_part = $drpsmstrdb.drps_part AND $ptpdetdb.ptp_site = $drpsmstrdb.drps_site")
            ->where($ptpCond)
            ->field("$drpsmstrdb.id")
            ->select()
            ;
            $drpIds = $drpIds ? $drpIds: [];
            array_walk($drpIds, function(&$item) {
                $item = $item["id"];
            });
            
            // update the comps day and week and month ismrp flags
            $parDmrpIds = $ptp_ids;
            $this->setParPartMrpFlagsOfIds($ptp_ids);
            $this->setCompMrpFlagsOfParPartIds($ptp_ids);
            
            $ptp->commit();
            
            // send mail to related comp buyers
            //$this->sendMailToCompBuyersOfParPartIds($ptp_ids);
        } catch (\Exception $e) {
            $ptp->rollback();
            $err = true;
            $msg = $e->getMessage();
        }
        

        
        $data = new \stdClass();
        $data->statusCode = $err ? 300 : 200;
        $data->message = $msg ?  $msg : "未进行任何操作";
        $this->ajaxReturn($data);
    }
    
    /**
     * 更新生产计划
     * @throws \Exception
     */
    public function saveRps ()
    {  
        $udata = $idata = []; 
        $parMrpIds = $parDmrpIds = [];
        $mparts = [];
        foreach ($_REQUEST as $key => $val) { 
            list($rtype, $rid, $rtm, $rpart, $rsite, $pid) = explode("#", $key); 
            
            // bug fix!! important!
            $rpart=str_replace('_', '.', $rpart);
            
            
            $rtype = strtolower($rtype); 
            if ($rtype != 'drp') {
                continue;
            }
            if (strpos($val, "-") < 1) {
                continue;
            }
            list($qty, $fqty) = explode("-", $val);
            $parMrpIds[] = $pid;
            $parDmrpIds[] = $pid;
 
            if (is_numeric($rid) && $rid != 0) {
                $mparts[] = $rpart;
                $udata["drp"][] = [
                        "id" => intval($rid),
                        "drps_qty" => $qty,
                        "drps_fqty" => $fqty,
                        "drps_part" => $rpart,
                        "drps_site" => $rsite,
                        "drps_date" => $rtm,
                        "ptp_id" => $pid
                ];
            } else {
                $idata["drp"][] = [
                        "drps_part" => $rpart,
                        "drps_site" => $rsite,
                        "drps_date" => $rtm,
                        "drps_qty" => $qty
                ];
            }
        }
        
        ksort($udata);
        ksort($idata);

        $err = false;
        $msg = '';
        try {
            // update $udata to db
            $ptp = D("PtpDet");
            $ptp->startTrans();
            $drp = D("DrpsMstr");
            foreach ($udata as $rtype => $rpsData)  {
                $num = 0;
                $drpsParParts = [];
                foreach ($rpsData as $drpData) {
                    if (!$drp->create($drpData)) {
                        $ptp->rollback();
                        throw new \Exception($drp->getError());
                    }
                    $data = [
                            "id" => $drpData["id"],
                            "drps_qty" => $drpData["drps_qty"],
                            "drps_ismrp" => true
                    ];
                    $num += $drp->save($data);
                
                
                    // update the change record
                    $dmrd = M("dmrd_det");
                    $dmrd->dmrd_site = $drpData["drps_site"];
                    $dmrd->dmrd_part = $drpData["drps_part"];
                    $dmrd->dmrd_tqty = $drpData["drps_qty"];
                    $dmrd->dmrd_fqty = $drpData["drps_fqty"];
                    $dmrd->dmrd_date = $drpData["drps_date"];
                    $dmrd->dmrd_mtime = date("Y-m-d H:i:s");
                    $dmrd->dmrd_user = session("username");
                    $dmrd->add();
                }
                
                
                
                $msg = "已更新： 日记录数：$num";
            }
            
            // insert $idata to db
            // ....
            
            
            // update pars ismrp and ptp_mtime
            $parMrpIds = array_unique($parMrpIds);
            $this->setParPartMrpFlagsOfIds($parMrpIds);
 
            // update comps is_dmrp 
            if (!empty($parDmrpIds)) { 
                $parDmrpIds = array_unique($parDmrpIds);
                $this->setCompMrpFlagsOfParPartIds($parDmrpIds);
            }
             
            $ptp->commit();
            
            // send mail to related comp buyers
            //$this->sendMailToCompBuyersOfParPartIds($parMrpIds);
        } catch (\Exception $e) {
            $ptp->rollback();
            $err = true;
            $msg = $e->getMessage();
        }
        
        
        
        
        $data = new \stdClass();
        $data->statusCode = $err ? 300 : 200;
        $data->message = $msg ?  $msg : "未进行任何操作";
        $this->ajaxReturn($data);
    }



    

    public function showRpsUpload()
    {
        $this->display();
    }
    

    
    /**
     * @throws \Exception
     * @return string
     */
    protected function getUploadedRpsFiles (array &$orgFileNames)
    {
        $upload = new \Think\Upload();
        $upload->maxSize   =     30000000 ;
        $upload->exts      =     array('xls', 'xlsx');
        $upload->rootPath  =     './Uploads/rps/';
        $upload->autoSub = true;
        $upload->subName = array('date','Ymd');
        //$upload->saveName = 'rps_' . time().'_'.mt_rand();;
    
        $info = $upload->upload();
        $filepaths = [];
        if (!$info) {
            throw new \Exception($upload->getError());
        } else { 
            foreach ($info as $file) {
                $filepath = $upload->rootPath . $file['savepath'] . $file['savename'];
                $filepaths[] = $filepath;
                $orgFileNames[$filepath] = $file['name'];
            }
        }
        
        return $filepaths;
    }
    
    
    public function importRpsExcels ($linesAllowed = '')
    {
        set_time_limit(300);
        
        $override = filter_var($_REQUEST["override"],FILTER_VALIDATE_BOOLEAN);
        $curSite = intval($_REQUEST["site"]);
        
        if (!empty($linesAllowed)) {
            if (is_string($linesAllowed)) {
                $linesAllowed = [$linesAllowed];
            }
        
            $linesAllowed = array_map("strtoupper", $linesAllowed);
        
        }
        
        $start = time();
        $err = false;
        $msg = '';
        $ptp = D("PtpDet");
        $lndDet = M("lnd_det");
        $drp = D("DrpsMstr");
        try {
            $ptpPlaner = self::getCurrentPlaner();
            
            $orgFileNames = [];
            $filepaths = $this->getUploadedRpsFiles($orgFileNames);
            $rows = [];
            
            $ptp->startTrans();
            // override privous rps if needed
            
            $drp->lock(true);
            if ($override) {
                if (empty($ptpPlaner)) {
                    // as admin to delete all previous rps of specified site
                    if ($curSite) {
                        $drp->where("drps_site=" . $curSite)->delete();
                    } else {
                        $drp->where("1")->delete();
                    }
                } else {
                    // as planer to delete related previous rps
                    $subquery = "select ptp_part from xy_ptp_det where ptp_buyer='$ptpPlaner'";
                    $drp->where("drps_part in ($subquery)")->delete();
                }
            }
            
            
            $nonMatchFileParts = [];
            $nonMatchPlanerFileParts = [];
            $nonMatchPartFileLines = [];
            foreach ($filepaths as $filepath) {
                $filename = $orgFileNames[$filepath];
                //$filename = basename($filepath);
                
                $rows = $this->xlsin($filepath);
                array_walk_recursive($rows, function(&$val) {
                    $val = trim($val);
                });
                
                
                // read the first row as headers.
                $heads = array_shift($rows);
            
                // ensure the date headers appeared in order,
                $mrpedData = [];
                $drpsMap = $wrpsMap = $mrpsMap = [];
                foreach ($heads as $key => $head) {
                    if (preg_match('#(\d{2}|\d{4})[^\d](\d{1,2})[^\d](\d{1,2})#', $head, $match)) {
                        // 解析天格式标题，并转换日期格式为yyyy-mm-dd，以便进行日期直接比较。
                        $y = $match[1];
                        if (strlen($y) == 2) {
                            $y = '20' . $y;
                        }
                        $m = $match[2];
                        $d = $match[3];
                        $drpsMap[$key] = sprintf("%04d-%02d-%02d", $y, $m, $d);
                    } else if (preg_match('#(\d{1,2}年(\d{1,2})周)#', $head, $match)) {
                        // 解析周格式标题，并转换格式为yy年ww周
                        $y = $match[1];
                        $w = $match[2];
                        $wrpsMap[$key] = sprintf("%02d年%02d周", $y, $w);
                    } else if (preg_match('#(\d{1,2}年(\d{1,2})月)#', $head, $match)) {
                        // 解析月格式标题，并转换格式为yy年mm月
                        $y = $match[1];
                        $m = $match[2];
                        $mrpsMap[$key] = sprintf("%02d年%02d月", $y, $m);
                    }
                }
            
            
                // enforce the date headers appear in order,
                $lastDay = '0000-00-00';
            
                foreach ($drpsMap as $day) {
                    if ($day <= $lastDay) {
                        throw new \Exception("illegal dates order provided");
                    }
                    $lastDay = $day;
                }
            
                // treat the week heads as Monday date just after the maximum date header in order
                // and ignore initial week value
                // 限定只取三周且必取三周
                if (count($wrpsMap) < 3) {
                    $wrpsMap = array_pad($wrpsMap, 3, 0);
                } else if (count($wrpsMap) > 3) {
                    $wrpsMap = array_slice($wrpsMap, 0, 3, true);
                }
                $maxDay = max($drpsMap);
                foreach ($wrpsMap as $key => $week) {
                    $wrpsMap[$key] = $maxDay = self::getMondayAfterDate($maxDay);
                }
            
                // treat the month heads as first date of the months lying just after the maximum week header in order
                // and ignore initial month value
                if (count($mrpsMap) < 3) {
                    $mrpsMap = array_pad($mrpsMap, 3, 0);
                } else if (count($mrpsMap) > 3) {
                    $mrpsMap = array_slice($mrpsMap, 0, 3, true);
                }
                foreach ($mrpsMap as $key => $month) {
                    $mrpsMap[$key] = $maxDay = self::getFirstDayOfMonthAfterDate($maxDay);
                }
            
            
                $drpsData = [];
                $parts = [];
                $partLineMap = [];
                $nonMatchParts = [];
                $nonMatchPlanerParts = [];
                $nonMatchPartLines = [];
                foreach ($rows as $row) {
                    // excel文件中B单元格为空则视为无效行忽略
                    if (empty($row["B"])) {
                        continue;
                    }
            
            
                    // 前3列分别为零件号、地点、生产线
                    $part = trim($row["A"]);
                    if (empty($part)) continue;
            
                    $site = trim($row["B"]);
                    if (empty($site)) continue;
                    if ($curSite && $site != $curSite) continue;
            
                    $line = strtoupper(ltrim($row["C"], '0'));  // 去除生产线单元格中的前置0
                    if (empty($line)) continue;
            
                    // 如果提供了只允许的产线，则忽略不被允许的产线
                    if (!empty($linesAllowed)) {
                        if (!in_array($line, $linesAllowed)) {
                            continue;
                        }
                    }
            
                    // 如果计划产量列全为0，忽略
                    $hasValue = 0;
                    for ($colOrd = ord('C'); isset($row[chr($colOrd)]); $colOrd++) {
                        if (floatval($row[chr($colOrd)])) {
                            $hasValue = 1;
                            break;
                        }
                    }
                    if (!$hasValue) continue;
            
            

            
            
                    // 逐条判断物料数据表关联记录是否存在
                    $where = $bind = [];
                    $where['ptp_part'] = ':ptp_part';
                    $where['ptp_site'] = ':ptp_site';
                    $bind[':ptp_part']    =  array($part,\PDO::PARAM_STR);
                    $bind[':ptp_site']    =  array($site,\PDO::PARAM_STR);
                    if ($ptp->where($where)->bind($bind)->count() == 0) {
                        $nonMatchParts[] = $part;
                        continue;
                        //$ptp->rollback();
                        //throw new \Exception("导入的生产计划中的零件号: $part 在物料参数表中不存在");
                    }
            
            
                    // 对计划员而非主管或管理员的导入应该检查导入的物料和计划员代码是否匹配
                    if (!empty($ptpPlaner)) {
                        $where = $bind = [];
                        $where['ptp_site'] = ':ptp_site';
                        $where['ptp_part'] = ':ptp_part';
                        $where['ptp_buyer'] = ':ptp_buyer';
                        $bind[':ptp_site']    =  array($site,\PDO::PARAM_STR);
                        $bind[':ptp_part']    =  array($part,\PDO::PARAM_STR);
                        $bind[':ptp_buyer']    =  array($ptpPlaner,\PDO::PARAM_STR);
                        if ($ptp->where($where)->bind($bind)->count() == 0) {
                            $nonMatchPlanerParts[] = $part;
                            continue;
                            //$ptp->rollback();
                            //throw new \Exception("导入的生产计划中的零件号: $part 与导入计划员: $ptpPlaner 在物料数据表中不匹配");
                        }
                    }
            
                    // 逐条判断物料和产线是否匹配
                    $where = $bind = [];
                    $where['lnd_part'] = ':lnd_part';
                    $where['lnd_line'] = ':lnd_line';
                    $bind[':lnd_part']    =  array($part,\PDO::PARAM_STR);
                    $bind[':lnd_line']    =  array($line,\PDO::PARAM_STR);
                    if ($lndDet->where($where)->bind($bind)->count() == 0) {
                        $nonMatchPartLines[$part][] = $line;
                        continue;
                        //$ptp->rollback();
                        //throw new \Exception("导入的生产计划中的零件号: $part 与产线 $line 不匹配");
                    }
            
            
            
                    $mrpedData[] = [
                            'site' => $site,
                            'part' => $part
                    ];
            
                    $partLineMap[$part][] = $line;
            
                    foreach ($drpsMap as $key => $day) {
                        $drpsData[] = [
                                "drps_part" => $part,
                                "drps_site" => $site,
                                "drps_line" => $line,
                                "drps_date" => $day,
                                "drps_qty"  => intval($row[$key]),
                                "drps_type" => 'd'
                        ];
                    }
                    foreach ($wrpsMap as $key => $day) {
                        $drpsData[] = [
                                "drps_part" => $part,
                                "drps_site" => $site,
                                "drps_line" => $line,
                                "drps_date" => $day,
                                "drps_qty"  => intval($row[$key]),
                                "drps_type" => 'w'
                        ];
                    }
                    foreach ($mrpsMap as $key => $day) {
                        $drpsData[] = [
                                "drps_part"  => $part,
                                "drps_site"  => $site,
                                "drps_line"  => $line,
                                "drps_date"  => $day,
                                "drps_qty"   => intval($row[$key]),
                                "drps_type" => 'm'
                        ];
                    }
                }
            
            
                // save to db
            
                 
            

                foreach ($drpsData as $drpData) {
                    $part = $drpData['drps_part'];
                    $drpData = $drp->create($drpData);
                    if (!$drpData) {
                        $ptp->rollback();
                        throw new \Exception("日生产计划数据验证失败");
                    }
            
                    $condition['drps_part'] = $drpData['drps_part'];
                    $condition['drps_site'] = $drpData['drps_site'];
                    $condition['drps_date'] = $drpData['drps_date'];
                    if ($drp->where($condition)->count()) {
                        $drp->where($condition)->save();
                    } else {
                        $drp->add();
                    }
                }

            
            
            
            
                // 设置导入的物料的已mrp标志为真
                foreach ($mrpedData as $pair) {
                    $site = $pair["site"];
                    $part = $pair["part"];
            
                    $where = $bind = [];
                    $where['ptp_part'] = ':ptp_part';
                    $where['ptp_site'] = ':ptp_site';
                    $bind[':ptp_part']    =  array($part,\PDO::PARAM_STR);
                    $bind[':ptp_site']    =  array($site,\PDO::PARAM_STR);
            
                    $ptp->where($where)->bind($bind)->save([
                            "ptp_isdmrp" => 0
                    ]);
                }
                
                // 重新计算所有采购件的mrp
                $ptp->where([
                        "ptp_site" => $site,
                        "ptp_pm_code" => "P"
                ])->save([
                        "ptp_isdmrp" => 1
                ]);
                
                $partCount += count($mrpedData);
                
                $nonMatchParts && $nonMatchFileParts[$filename] = $nonMatchParts;
                $nonMatchPlanerParts && $nonMatchPlanerFileParts[$filename] = $nonMatchPlanerParts;
                $nonMatchPartLines && $nonMatchPartFileLines[$filename] = $nonMatchPartLines;
            }
            
 
            
            $ptp->commit();
            $msg = "已成功导入$partCount 条生产计划";
            
            if ($nonMatchFileParts) {
                foreach ($nonMatchFileParts as $file => $nonMatchParts) {
                    $msg .= "<br />生产计划文件 <em>{$file}</em> 中导入了在物料表中不存在的零件号: " . implode(", ", $nonMatchParts);
                }
            }
            
            if ($nonMatchPlanerFileParts) {
                foreach ($nonMatchPlanerFileParts as $file => $nonMatchPlanerParts) {
                    $msg .= "<br />生产计划文件 <em>{$file}</em> 中导入了与计划员不匹配的零件号: " . implode(", ", $nonMatchPlanerParts);
                }
            }
            
            if ($nonMatchPartFileLines) {
                foreach ($nonMatchPartFileLines as $file => $nonMatchPartLines) {
                    $msg .= "<br />生产计划文件 <em>{$file}</em> 中导入了与产线不匹配的零件号: ";
                    foreach ($nonMatchPartLines as $part => $lines) {
                        foreach ($lines as $line) {
                            $msg .= " '$part->'$line'    ";
                        }
                    }
                }


            }
            
            $end = time();
            $duration = $end - $start;
            //$msg .= "， 耗时{$duration}秒";

            $this->success($msg, '', 60);
        } catch (\Exception $e) {
            $ptp->rollback();
            $err = true;
            $msg = $e->getMessage();
            
            $end = time();
            $duration = $end - $start;
            //$msg .= "， 耗时{$duration}秒";
            
            $this->error($msg, '', 120);
        }
        
        
//         $data = new \stdClass();
//         $data->statusCode = $err ? 300 : 200;
//         $data->message = $msg ?  $msg : "未进行任何操作";
//         $this->ajaxReturn($data);
        

    }
    
    
    /**
     * @throws \Exception
     * @return string
     */
    protected function getUploadedRpsFile ()
    {
        $upload = new \Think\Upload();
        $upload->maxSize   =     30000000 ;
        $upload->exts      =     array('xls', 'xlsx');
        $upload->rootPath  =     './Uploads/rps/';
        $upload->autoSub = true;
        $upload->subName = array('date','Ymd');
        $upload->saveName = 'rps_' . time().'_'.mt_rand();;
    
        $info = $upload->upload();
        if(!$info) {
            throw new \Exception($upload->getError());
        } else {
            if (count($info) > 1) {
                throw new \Exception("错误：一次只允许上传一个生产计划文件");
            }
            $file = current($info);
            return $upload->rootPath . $file['savepath'].$file['savename'];
        }
    }
    
    
    /**
     * 导入生产计划表
     * @param $override: 是否进行覆盖式导入
     * @throws \Exception
     */
    public function importRps ($linesAllowed = '')
    {
        set_time_limit(300);
        
        $override = filter_var($_REQUEST["override"],FILTER_VALIDATE_BOOLEAN);
        
        if (!empty($linesAllowed)) {
            if (is_string($linesAllowed)) {
                $linesAllowed = [$linesAllowed];
            }
        
            $linesAllowed = array_map("strtoupper", $linesAllowed);
        
        }
        
        $start = time();
        $err = false;
        $msg = '';
        $ptp = D("PtpDet");
        $lndDet = M("lnd_det");
        try {
            $ptpPlaner = self::getCurrentPlaner();
            
            $filepath = $this->getUploadedRpsFile();
            // parse from excel file.
            $rows = $this->xlsin($filepath);
            array_walk_recursive($rows, function(&$val) {
                $val = trim($val);
            });

            
            // read the first row as headers.
            $heads = array_shift($rows);
            
            // ensure the date headers appeared in order,

            
            $mrpedData = [];
            $drpsMap = $wrpsMap = $mrpsMap = [];
            foreach ($heads as $key => $head) {
                if (preg_match('#(\d{2}|\d{4})/(\d{1,2})/(\d{1,2})#', $head, $match)) {
                    // 解析天格式标题，并转换日期格式为yyyy-mm-dd，以便进行日期直接比较。
                    $y = $match[1];
                    if (strlen($y) == 2) {
                        $y = '20' . $y;
                    }
                    $m = $match[2]; 
                    $d = $match[3];
                    $drpsMap[$key] = sprintf("%04d-%02d-%02d", $y, $m, $d);
                } else if (preg_match('#(\d{1,2}年(\d{1,2})周)#', $head, $match)) {
                    // 解析周格式标题，并转换格式为yy年ww周
                    $y = $match[1];
                    $w = $match[2];
                    $wrpsMap[$key] = sprintf("%02d年%02d周", $y, $w);
                } else if (preg_match('#(\d{1,2}年(\d{1,2})月)#', $head, $match)) {
                    // 解析月格式标题，并转换格式为yy年mm月
                    $y = $match[1];
                    $m = $match[2];
                    $mrpsMap[$key] = sprintf("%02d年%02d月", $y, $m);
                }
            }

            
            // enforce the date headers appear in order, 
            $lastDay = '0000-00-00';

            foreach ($drpsMap as $day) {
                if ($day <= $lastDay) {
                    throw new \Exception("illegal dates order provided");
                }
                $lastDay = $day;
            }
            
            // treat the week heads as Monday date just after the maximum date header in order
            // and ignore initial week value
            // 限定只取三周且必取三周
            if (count($wrpsMap) < 3) {
                $wrpsMap = array_pad($wrpsMap, 3, 0);
            } else if (count($wrpsMap) > 3) {
                $wrpsMap = array_slice($wrpsMap, 0, 3, true);
            }
            $maxDay = max($drpsMap);
            foreach ($wrpsMap as $key => $week) {
                $wrpsMap[$key] = $maxDay = self::getMondayAfterDate($maxDay);
            }
            
            // treat the month heads as first date of the months lying just after the maximum week header in order
            // and ignore initial month value
            if (count($mrpsMap) < 3) {
                $mrpsMap = array_pad($mrpsMap, 3, 0);
            } else if (count($mrpsMap) > 3) {
                $mrpsMap = array_slice($mrpsMap, 0, 3, true);
            }
            foreach ($mrpsMap as $key => $month) {
                $mrpsMap[$key] = $maxDay = self::getFirstDayOfMonthAfterDate($maxDay);
            }
            
            $ptp->startTrans();
            $drpsData = [];
            $parts = [];
            $partLineMap = [];
            $nonMatchParts = [];
            $nonMatchPlanerParts = [];
            $nonMatchPartLines = [];
            foreach ($rows as $row) {
                // excel文件中B单元格为空则视为无效行忽略
                if (empty($row["B"])) {
                    continue;
                }
                
                
                // 前3列分别为零件号、地点、生产线
                $part = trim($row["A"]);
                if (empty($part)) continue;
                
                $site = trim($row["B"]);
                if (empty($site)) continue;
                
                $line = strtoupper(ltrim($row["C"], '0'));  // 去除生产线单元格中的前置0
                if (empty($line)) continue;
                
                // 如果提供了只允许的产线，则忽略不被允许的产线
                if (!empty($linesAllowed)) {
                    if (!in_array($line, $linesAllowed)) {
                        continue;
                    }
                }
                
                // 如果计划产量列全为0，忽略
                $hasValue = 0;
                for ($colOrd = ord('C'); isset($row[chr($colOrd)]); $colOrd++) {
                    if (floatval($row[chr($colOrd)])) {
                        $hasValue = 1;
                        break;
                    }
                }
                if (!$hasValue) continue;
                
                
                
                
                
                // 逐条判断物料数据表关联记录是否存在
                $where = $bind = [];
                $where['ptp_part'] = ':ptp_part';
                $where['ptp_site'] = ':ptp_site';
                $bind[':ptp_part']    =  array($part,\PDO::PARAM_STR);
                $bind[':ptp_site']    =  array($site,\PDO::PARAM_STR);
                if ($ptp->where($where)->bind($bind)->count() == 0) {
                    $nonMatchParts[] = $part;
                    continue;
                    //$ptp->rollback();
                    //throw new \Exception("导入的生产计划中的零件号: $part 在物料参数表中不存在");
                }  
                
                
                // 对计划员而非主管或管理员的导入应该检查导入的物料和计划员代码是否匹配
                if (!empty($ptpPlaner)) {
                    $where = $bind = [];
                    $where['ptp_site'] = ':ptp_site';
                    $where['ptp_part'] = ':ptp_part';
                    $where['ptp_buyer'] = ':ptp_buyer';
                    $bind[':ptp_site']    =  array($site,\PDO::PARAM_STR);
                    $bind[':ptp_part']    =  array($part,\PDO::PARAM_STR);
                    $bind[':ptp_buyer']    =  array($ptpPlaner,\PDO::PARAM_STR);
                    if ($ptp->where($where)->bind($bind)->count() == 0) {
                        $nonMatchPlanerParts[] = $part;
                        continue;
                        //$ptp->rollback();
                        //throw new \Exception("导入的生产计划中的零件号: $part 与导入计划员: $ptpPlaner 在物料数据表中不匹配");
                    }
                }
                
                // 逐条判断物料和产线是否匹配
                $where = $bind = [];
                $where['lnd_part'] = ':lnd_part';
                $where['lnd_line'] = ':lnd_line';
                $bind[':lnd_part']    =  array($part,\PDO::PARAM_STR);
                $bind[':lnd_line']    =  array($line,\PDO::PARAM_STR);
                if ($lndDet->where($where)->bind($bind)->count() == 0) {
                    $nonMatchPartLines[$part][] = $line;
                    continue;
                    //$ptp->rollback();
                    //throw new \Exception("导入的生产计划中的零件号: $part 与产线 $line 不匹配");
                }
                
                
                
                $mrpedData[] = [
                        'site' => $site,
                        'part' => $part
                ];
                
                $partLineMap[$part][] = $line;
                
                foreach ($drpsMap as $key => $day) {
                    $drpsData[] = [
                            "drps_part" => $part,
                            "drps_site" => $site,
                            "drps_line" => $line,
                            "drps_date" => $day,
                            "drps_qty"  => intval($row[$key]),
                            "drps_type" => 'd'
                    ];
                }
                foreach ($wrpsMap as $key => $day) {
                    $drpsData[] = [
                            "drps_part" => $part,
                            "drps_site" => $site,
                            "drps_line" => $line,
                            "drps_date" => $day,
                            "drps_qty"  => intval($row[$key]),
                            "drps_type" => 'w'
                    ];
                }
                foreach ($mrpsMap as $key => $day) {
                    $drpsData[] = [
                            "drps_part"  => $part,
                            "drps_site"  => $site,
                            "drps_line"  => $line,
                            "drps_date"  => $day,
                            "drps_qty"   => intval($row[$key]),
                            "drps_type" => 'm'
                    ];
                }
            }
            

            // save to db

           
            
            // override privous rps if needed
            $drp = D("DrpsMstr"); 
            $drp->lock(true);
            if ($override) {
                if (empty($ptpPlaner)) {
                    // as admin to delete all previous rps of specified site
                    if ($_REQUEST["site"]) {
                        $drp->where("drps_site=" . $_REQUEST["site"])->delete();
                    } else {
                        $drp->where("1")->delete();
                    }
                } else {
                    // as planer to delete related previous rps
                    $subquery = "select ptp_part from xy_ptp_det where ptp_buyer='$ptpPlaner'";
                    $drp->where("drps_part in ($subquery)")->delete();
                }
            }
            foreach ($drpsData as $drpData) {
                $part = $drpData['drps_part'];
                $drpData = $drp->create($drpData);
                if (!$drpData) {
                    $ptp->rollback();
                    throw new \Exception("日生产计划数据验证失败");
                }
                
                $condition['drps_part'] = $drpData['drps_part'];
                $condition['drps_site'] = $drpData['drps_site'];
                $condition['drps_date'] = $drpData['drps_date'];
                if ($drp->where($condition)->count()) {
                    $drp->where($condition)->save();
                } else {
                    $drp->add();
                }
            }
            unset($drp);
            
            
            
            
            // 设置导入的物料的已mrp标志为真
            foreach ($mrpedData as $pair) {
                $site = $pair["site"];
                $part = $pair["part"];
                
                $where = $bind = [];
                $where['ptp_part'] = ':ptp_part';
                $where['ptp_site'] = ':ptp_site';
                $bind[':ptp_part']    =  array($part,\PDO::PARAM_STR);
                $bind[':ptp_site']    =  array($site,\PDO::PARAM_STR);
                
                $ptp->where($where)->bind($bind)->save([
                        "ptp_site" => $site,
                        "ptp_part" => $part,
                        "ptp_isdmrp" => 0
                ]);
            }
            
            // 重置ptp_det表所有采购件为需要计算mrp状态。
            $pmodel = new Model();
            $sql = 'update xy_ptp_det set ptp_ismrp=0,ptp_isdmrp=1,ptp_iswmrp=1,ptp_ismmrp=1 where ptp_pm_code="P"';
            $pmodel->execute($sql);
            
            
            $ptp->commit();
            $partCount = count($mrpedData);
            $msg = "已成功导入$partCount 条生产计划";
            
            if ($nonMatchParts) {
                $msg .= "<br />生产计划中导入了在物料表中不存在的零件号: " . implode(", ", $nonMatchParts);
            }
            
            if ($nonMatchPlanerParts) {
                $msg .= "<br />生产计划中导入了与计划员不匹配的零件号: " . implode(", ", $nonMatchPlanerParts);
            }
            
            if ($nonMatchPartLines) {
                $msg .= "<br />生产计划中导入了与产线不匹配的零件号: ";
                foreach ($nonMatchPartLines as $part => $lines) {
                    foreach ($lines as $line) {
                        $msg .= " '$part->'$line'    ";
                    }
                }

            }
            
            $end = time();
            $duration = $end - $start;
            //$msg .= "， 耗时{$duration}秒";

            $this->success($msg, '', 30);
        } catch (\Exception $e) {
            $ptp->rollback();
            $err = true;
            $msg = $e->getMessage();
            
            $end = time();
            $duration = $end - $start;
            //$msg .= "， 耗时{$duration}秒";
            
            $this->error($msg, '', 120);
        }
        
        
//         $data = new \stdClass();
//         $data->statusCode = $err ? 300 : 200;
//         $data->message = $msg ?  $msg : "未进行任何操作";
//         $this->ajaxReturn($data);
        

    }

    public function exportRpsCsv() 
    {
        $ptpBuyer = self::getCurrentPlaner();

        $data = [];
        $model = M($this->dbname);
        
        // 只导出条件限定的记录
        $map = $this->_search();


        $result = $model->where($map)->order('id')->select(); 
       
        $dates = $this->getDates();
        
        $parts = self::convertToPlansFromDbResult($result);  
        if (empty($parts)) {
            throw new \Exception("计划员:$ptpBuyer 的生产计划不存在");
        }
       
        
        // get headers
        $dheaders = $this->getFormattedDatesMap();

        
        $list = [];
        
        
        $titleItems = [
                "",
                "",
                "",
                "",
                "",
        ];
        $titleItems = array_merge($titleItems, $dheaders);
        $list[] = $titleItems;
        
       // write csv title line
        $titleItems = [
                "地点",
                "零件号",
                "料号描述",
                "描述2",
                "产线", 
        ];
        $titleItems = array_merge($titleItems, $dates);
        $list[] = $titleItems;
        

        
        foreach ($parts as $part) {
            $item = [
                    $part["ptp_site"],
                    $part["ptp_part"],
                    $part["ptp_desc1"],
                    $part["ptp_desc2"],
                    $part["drps_line"],
            ];
            foreach ($part['drps'] as $date => $drp) {
                $item[] = $drp['drps_qty'];
            }
            
            $list[] = $item;
        }
        

        $today = date("Y_m_d");
        if ($ptpBuyer) {
            $filename = "rps-$ptpBuyer-$today.csv";
        } else {
            $filename = "rps-$today.csv";
        }

        $filename = iconv("utf-8", 'gbk', $filename);
        $this->exportCSV($filename, $list);
    }
    
    public static function isMonday ($date)
    {
        return date("N", strtotime($date)) == 1;
    }
    
    public static function isMonthFirstDay ($date)
    {
        return date("d", strtotime($date)) == 1;
    }
    
    protected static function getMondayAfterDate($dateStr, $skipWeeks = 0)
    {
        $date = new \DateTime($dateStr);
        $wd = $date->format("w");
        
        switch ($wd) {
            case 0:
                $date->add(new \DateInterval("P1D"));
                break;
            default:
                $intervalDay = 8 - $wd;
                $date->add(new \DateInterval("P{$intervalDay}D"));
        }
        
        if ($skipWeeks) {
            $date->add(new \DateInterval("P{$skipWeeks}W"));
        }
        return $date->format("Y-m-d");
    }
    
    protected static function getWeekOfDate($dateStr)
    {
        $date = new \DateTime($dateStr);
        return $date->format("W");
    }
    
    protected static function getFirstDayOfMonthAfterDate ($dateStr)
    {
        $m = date("m", strtotime($dateStr));
        $y = date("Y", strtotime($dateStr));
        
        if ($m == 12) {
            $y++;
            $m = 1;
        } else {
            $m++;
        }
        
        $date = "$y-$m-1";
        
        return date("Y-m-d", strtotime($date));
    }
    
    protected static function getFirstMondayOfMonthAfterDate ($dateStr)
    {
        $m = date("m", strtotime($dateStr));
        $y = date("Y", strtotime($dateStr));
        
        if ($m == 12) {
            $y++;
            $m = 1;
        } else {
            $m++;
        }
        
        return self::getFirstMondayOfMonth($m, $y);
        
    }
    
    protected static function getFirstMondayOfMonth ($month, $year)
    {
        if (empty($year)) {
            $year = date('Y');
        }
        $firstDay = "$year-$month-1";
        if (date("w", strtotime($firstDay)) == 1) {
            $firstMonday = $firstDay;
        } else {
            $firstMonday = self::getMondayAfterDate($firstDay);
        }

        return $firstMonday;
    }

    public function excelTime ($date, $time = false)
    {
        if (function_exists('GregorianToJD')) {
            if (is_numeric($date)) {
                $jd = GregorianToJD(1, 1, 1970);
                $gregorian = JDToGregorian($jd + intval($date) - 25569);
                $date = explode('/', $gregorian);
                $date_str = str_pad($date[2], 4, '0', STR_PAD_LEFT) . "-" . str_pad($date[0], 2, '0', STR_PAD_LEFT) . "-" . str_pad($date[1], 2, '0', STR_PAD_LEFT) .
                         ($time ? " 00:00:00" : '');
                return $date_str;
            }
        } else {
            $date = $date > 25568 ? $date + 1 : 25569;
            /*There was a bug if Converting date before 1-1-1970 (tstamp 0)*/
            $ofs = (70 * 365 + 17 + 2) * 86400;
            $date = date("Y-m-d", ($date * 86400) - $ofs) . ($time ? " 00:00:00" : '');
        }
        return $date;  
    }  

}