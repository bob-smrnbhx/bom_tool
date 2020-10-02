<?php
namespace Home\Controller;
use Think\Controller;
use Think\Model;

/**
 * 生产计划控制器
 * @author wz
 *
 */
class ProplanMoldController extends ProplanController
{
 

    public function _initialize ()
    {
        parent::_initialize();
        $this->dbname = "proplan";
    }
    

    
    protected function _search ()
    {

        $map = ["ptp_pm_code" => "L"];
        
        switch($_REQUEST["site"]) {
            case 1000:
                $map["ptp_site"] = '1000';
                $map["drps_line"] = 'M00001';
                break;
            case 6000:
                $map["ptp_site"] = '6000';
                $map["drps_line"] = 'M60001';
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

        // 获取格式化的日期标题
        $dHeadersMap = self::getFormattedDatesMap();
        // 获取指定地点的所有计划日期范围
        $dates = M("drps_mstr")->distinct(true)->field("drps_date")->where($this->getSiteCond())->getField("drps_date", true);
        
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
    public function importRps ()
    {
        set_time_limit(300);
        
        $override = filter_var($_REQUEST["override"],FILTER_VALIDATE_BOOLEAN);
        
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
            $maxDay = max($drpsMap);
            foreach ($wrpsMap as $key => $week) {
                $wrpsMap[$key] = $maxDay = self::getMondayAfterDate($maxDay);
            }
            
            // treat the month heads as Monday date of the months lying just after the maximum week header in order
            // and ignore initial month value
            $i = 0;
            foreach ($mrpsMap as $key => $month) {
                if ($i == 0) {
                    // the first month monday could lie in the same month of the max week Monday
                    $mrpsMap[$key] = $maxDay = self::getMondayAfterDate($maxDay);
                } else {
                    $mrpsMap[$key] = $maxDay = self::getFirstMondayOfMonthAfterDate($maxDay);
                }
                
                $i++;
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
                $line = ltrim($row["C"], '0');  // 去除生产线单元格中的前置0
                
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
                $bind[':ptp_part']    =  array($part,\PDO::PARAM_STR);
                if ($ptp->where($where)->bind($bind)->count() == 0) {
                    $nonMatchParts[] = $part;
                    continue;
                    //$ptp->rollback();
                    //throw new \Exception("导入的生产计划中的零件号: $part 在物料参数表中不存在");
                }
                
                
                // 对计划员而非主管或管理员的导入应该检查导入的物料和计划员代码是否匹配
                if (!empty($ptpPlaner)) {
                    $where = $bind = [];
                    $where['ptp_part'] = ':ptp_part';
                    $where['ptp_buyer'] = ':ptp_buyer';
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
                
            
                
                
                $parts[] = $part;
                
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
            $ptp->startTrans();
            
            
  
            
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
            
 
            
            
            // 重置所有物料mrp标志
            $model = new Model();
            $sql = 'update xy_ptp_det set ptp_ismrp=0,ptp_isdmrp=1,ptp_iswmrp=1,ptp_ismmrp=1 where 1=1;';
            $model->execute($sql);
            
            $ptp->commit();
            $partCount = count($parts);
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
        $dates = M("drps_mstr")->distinct(true)->field("drps_date")->where($this->getSiteCond())->getField("drps_date", true);
        $parts = self::convertToPlansFromDbResult($result);  
        if (empty($parts)) {
            throw new \Exception("计划员:$ptpBuyer 的生产计划不存在");
        }
       
        
        // get headers
        $dheaders = $this->getFormattedDatesMap();

        
        $list = [];
       // write csv title line
        $titleItems = [
                "零件号",
                "地点",
                //"产线", 
        ];
        $titleItems = array_merge($titleItems, $dheaders);
        $list[] = $titleItems;
        
        foreach ($parts as $part) {
            $item = [
                    $part["ptp_part"],
                    $part["ptp_site"],
                    //$part["ptp_line"],
            ];
            foreach ($part['drps'] as $date => $drp) {
                $item[] = $drp['drps_qty'];
            }
            
            $list[] = $item;
        }
        

        $today = date("Y_m_d");
        if ($ptpBuyer) {
            $filename = "生产计划-$ptpBuyer-$today.csv";
        } else {
            $filename = "生产计划-全部-$today.csv";
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
    
    function test()
    {
        dump($this->getFormattedDatesMap());
    }

}