<?php

namespace Home\Controller;
use Think\Controller;
use Think\Model;

class ProdmdAssyCQController extends Controller
{
    private $_proj;
    
    
    public function _initialize ()
    {
        if ($_REQUEST["proj"]) {
            $this->_proj = strtolower(trim($_REQUEST["proj"]));
        } else {
            $this->_proj = "c490";
        }
    }
    
    
    
    public function index ()
    {
        $adapter = new \Home\Model\AssyProdModel(6000, $this->_proj);
        
        $adapter->DoAssemblyMrp();
        
        
        $this->assign("proj", $this->_proj);
        $this->assign("orgDate", $adapter->getOrgDate());
        $this->assign("dates", $adapter->getActiveDates());
        $this->assign("dateTypeMap", $adapter->getDateTypeMap());
        $this->assign("isPeriodDateMap", $adapter->getAssyIsPeriodDateMap());
        $this->assign("isDoubleShiftDateMap", $adapter->getIsDoubleShiftDateMap());
        $this->assign("partsInfo", $adapter->getPartsInfo());
        $this->assign("isWorkdayDateMap", $adapter->getIsWorkdayDateMap());
        $this->assign("capacities", $adapter->getCapacities());
        $this->assign("totalDemands", $adapter->getTotalDayDmds());
        $this->assign("unusedCapacities", $adapter->getAvailCapacities());
        $this->assign("totalProds", $adapter->getTotalDayProds());
        $this->assign("useSaftyStockRule", $adapter->isSaftyStockRuleUsed());  
        $this->display();
    }
    
    public function exportBalanceExcel ()
    {
        $adapter = new \Home\Model\AssyProdModel(6000, $this->_proj);
        
        $adapter->exportBalanceExcel();
    }
    
    public function updatePlans()
    {
        $rpsInfo = [];
        $ptpsInfo = [];
        
        foreach ($_REQUEST as $key => $val) { 
            list($r, $rpart, $rline, $rsite, $rdate, $rtype) = explode("#", $key);
            $rpart=str_replace('_', '.', $rpart);
            
            
            
            $extra = '';
            if (strpos($rpart, "-") > 0) {
                list($rpart, $extra) = explode("-", $rpart);

            }
            
            // 所有活动日期的计划日程数据，哪怕为0，都应该更新，从而可以覆盖之前可能已存在的计划数据值。
           $drpsData = [
                    "drps_part" => $rpart,
                    "drps_line" => $rline,
                    "drps_site" => $rsite,
                    "drps_date" => $rdate,
                    "drps_qty"  => floatval($val),
                    "drps_type" => $rtype
           ];
           
           if ($extra) {
               $drpsData["drps_mach"] = $extra;  // 暂时用这个字段存储下安特和福特的标志
           }
           $rpsInfo[] = $drpsData;
            
            
            // 对应的地点-物料数据的mrp标志必须在更新后设置为0，表示已运行过mrp，不再需要重新运行。
            $ptpsInfo[$rpart . $rsite] = [
                    "ptp_site" => $rsite,
                    "ptp_part" => $rpart,
                    "ptp_isdmrp" => 0
            ];
            
            
        }
        

        
        $err = false;
        $msg = '';
        try {
            $drp = M("drps_mstr");
            $ptp = M("ptp_det");
            $drp->startTrans();
            

            foreach ($rpsInfo as $rpInfo) {
                $where = $bind = [];
                $where['drps_part'] = ':drps_part';
                $where['drps_line'] = ':drps_line';
                $where['drps_site'] = ':drps_site';
                $where['drps_date'] = ':drps_date';
 
                $bind[':drps_part']    =  array($rpInfo["drps_part"],\PDO::PARAM_STR);
                $bind[':drps_line']    =  array($rpInfo["drps_line"],\PDO::PARAM_STR);
                $bind[':drps_site']    =  array($rpInfo["drps_site"],\PDO::PARAM_STR);
                $bind[':drps_date']    =  array($rpInfo["drps_date"],\PDO::PARAM_STR);
                
                if ($rpInfo["drps_mach"]) {
                    $where['drps_mach'] = ':drps_mach';
                    $bind[':drps_mach']    =  array($rpInfo["drps_mach"],\PDO::PARAM_STR);
                }

                
                if ($drp->where($where)->bind($bind)->count() != 0) {
                    $drp->where($where)->bind($bind)->save($rpInfo);
                } else {
                    $drp->add($rpInfo);
                }
            }
            
            foreach ($ptpsInfo as $ptpInfo) {
                $where = $bind = [];
                $where['ptp_part'] = ':ptp_part';
                $where['ptp_site'] = ':ptp_site';
                
                $bind[':ptp_part']    =  array($ptpInfo["ptp_part"],\PDO::PARAM_STR);
                $bind[':ptp_site']    =  array($ptpInfo["ptp_site"],\PDO::PARAM_STR);
                
                $ptp->where($where)->bind($bind)->save($ptpInfo);
            }
            
 
            $drp->commit();
            $msg = "生产计划更新成功";
        } catch (\Exception $e) {
            $drp->rollback();
            $err = true;
            $msg = $e->getMessage();
        }

        $data = new \stdClass();
        $data->statusCode = $err ? 300 : 200;
        $data->message = $msg ?  $msg : "未进行任何操作";
        $this->ajaxReturn($data);
    }
    
 
    
 
    
    
 
    
    public function importDmds ()
    {
        set_time_limit(300);
        $override = filter_var($_REQUEST["override"],FILTER_VALIDATE_BOOLEAN);
        
        $start = time();
        $err = false;
        $msg = '';
        try {
            //$filepath = $this->getUploadedRpsFile();
            $filepath = 'C:\wamp\www\dev\Public\excelData\demand.xlsx';
            $rows = $this->xlsin($filepath, 0);
            array_walk_recursive($rows, function(&$val) {
                $val = trim($val);
            });
            // read the first row as header.
            $heads = array_shift($rows);
            
            $dMap = $wMap = $mMap = [];
            
            foreach ($heads as $key => $head) {
                if (preg_match('#(\d{2}|\d{4})/(\d{1,2})/(\d{1,2})#', $head, $match)) {
                    // 解析天格式标题，并转换日期格式为yyyy-mm-dd，以便进行日期直接比较。
                    $y = $match[1];
                    if (strlen($y) == 2) {
                        $y = '20' . $y;
                    }
                    $m = $match[2];
                    $d = $match[3];
                    $dMap[$key] = sprintf("%04d-%02d-%02d", $y, $m, $d);
                } else if (preg_match('#.+周$#', $head, $match)) {
                    // 解析周格式标题
                    $wMap[$key] = $match[0];
                } else if (preg_match('#.+月$#', $head, $match)) {
                    // 解析月格式标题
                    $mMap[$key] = $match[0];
                }
            }
            
            $lastDay = '0000-00-00';
            foreach ($dMap as $day) {
                if ($day <= $lastDay) {
                    throw new \Exception("illegal dates order provided");
                }
                $lastDay = $day;
            }
            
 
            $maxDay = max($dMap);
            foreach ($wMap as $key => $week) {
                $wMap[$key] = $maxDay = self::getMondayAfterDate($maxDay);
            }
            
 
            foreach ($mMap as $key => $month) {
                $mMap[$key] = $maxDay = self::getFirstDayOfMonthAfterDate($maxDay);
            }
            

            
 
            
            
            $allData = [];
            foreach ($rows as $row) {
                $part = $row["A"];
                $site = $row["B"];
 
                
                foreach ($dMap as $key => $day) {
                    $allData[] = [
                            "dmd_part" => $part,
                            "dmd_site" => $site,
                            "dmd_date" => $day,
                            "dmd_qty"  => intval($row[$key]),
                            "dmd_type" => 'd'
                    ];
                }
                
                foreach ($wMap as $key => $day) {
                    $allData[] = [
                            "dmd_part" => $part,
                            "dmd_site" => $site,
                            "dmd_date" => $day,
                            "dmd_qty"  => intval($row[$key]),
                            "dmd_type" => 'w'
                    ];
                }
                
                foreach ($mMap as $key => $day) {
                    $allData[] = [
                            "dmd_part" => $part,
                            "dmd_site" => $site,
                            "dmd_date" => $day,
                            "dmd_qty"  => intval($row[$key]),
                            "dmd_type" => 'm'
                    ];
                }
            }
            
            
            $ptp = M("ptp_det");
            
            $dmd = M("dmd_det");
            $dmd->startTrans();
            
            if ($override) {
                $dmd->where("1")->delete();
            }
            
            $unum = $inum = 0;
            $nonMatchParts = [];
            foreach ($allData as $data) {
                // check part existence and verify part type.
                $where = $bind = [];
                $where['ptp_part'] = $data["dmd_part"];
                $bind[':ptp_part']    =  array($data["dmd_part"],\PDO::PARAM_STR);
                if ($ptp->where($where)->bind($bind)->count() == 0) {
                    //$ptp->rollback();
                    $nonMatchParts[] = $data["dmd_part"];
                    //continue;
                    //throw new \Exception("导入的生产计划中的零件号: $part 在物料参数表中不存在");
                }
            
                $demandCond = [
                        'dmd_site' => $data["dmd_site"],
                        'dmd_part' => $data["dmd_part"],
                        'dmd_date' => $data["dmd_date"],
                ];
                if ($dmd->where($demandCond)->count()) {
                    $unum += $dmd->where($demandCond)->save($data);
                } else {
                    $lid = $dmd->add($data);
                    if ($lid > 0) {
                        $inum++;
                    }
                }
            }
            
            
            //                 $pmodel = new Model();
            //                 $sql = 'update xy_ptp_det set ptp_ismrp=1,ptp_isdmrp=1,ptp_iswmrp=1,ptp_ismmrp=1;';
            //                 $pmodel->execute($sql);
            
            $dmd->commit();
            
            $msg = "已成功导入客户需求<br />";
            if ($nonMatchParts) {
                $nonMatchParts = array_unique($nonMatchParts);
                $msg .= "如下零件号在物料主数据表中不存在： <br />" . implode(", ", $nonMatchParts);
            }
            $this->success($msg, '', 30);
        } catch (\Exception $e) {
            if ($dmd) {
                $dmd->rollback();
            }
            $msg = $e->getMessage();
            $this->error($msg, '', 120);
        }
    }
    
    
    
    function assemblyBalanceTable ()
    {
        $model = M("assy_dmd");
        
        switch($_REQUEST["site"]) {
            case 1000:
                $map["site"] = '1000';
                break;
            case 6000:
                $map["site"] = '6000';
                break;
        }

        $dates = $model->field("date")->where($map)->getField("date", true);
        $dates = array_unique($dates);
        sort($dates);
        
        $results = $model->order("date")->where($map)->select();
        $parts = [];
        foreach ($results as $item) {
            $uuid = $item["part"] . "-" . $item["site"];
            if (!isset($parts[$uuid])) {
                $parts[$uuid] = [
                      "id" => $uuid,
                      "site" => $item["site"],
                      "part" => $item["part"], 
                      "desc1" => $item["desc1"],
                      "desc2" => $item["desc2"],
                ];
            }
            
            $date = $item["date"];
            if (!isset($parts[$uuid][$date])) {
                $parts[$uuid][$date] = [
                        "dmd_qty" => $item["dmd_qty"],
                        "plan_qty" => $item["plan_qty"],
                        "inter_qty" => $item["inter_qty"],
                        "exter_qty" => $item["exter_qty"],
                        "total_qty" => $item["total_qty"],
                        "stock_qty" => $item["stock_qty"],
                ];
            }
        }
        
        
        $this->assign("dates", $dates);
        $this->assign("parts", $parts);
        $this->assign("condFields", $this->_allowedCondFields);

        $this->display();
    }
    
    function paintingBalanceTable ($date = '')
    {
        $model = M("painting_dmd");
    

        switch($_REQUEST["site"]) {
            case 1000:
                $map["site"] = '1000';
                break;
            case 6000:
                $map["site"] = '6000';
                break;
        }
    
        
        if (empty($date)) {
            // get the closest date as default
            $date = $model->where($map)->max("date");
        }
        $map["date"] = $date;
    
        $results = $model->order("circle, no")->where($map)->select();
        $cParts = [];
        $circleNames = [];
        foreach ($results as $item) {
            $circle = $item["circle"];
            if (!isset($circleNames[$circle])) {
                $circleNames[$circle] = "第{$circle}圈";
            }

            unset($item["circle"]);
            $cParts[$circle][] = $item;
                
        }
    
        $this->assign("date", $date);
        $this->assign("circleNames", $circleNames);
        $this->assign("cParts", $cParts);
        
        $this->display();
    }
    
    function mouldingBalanceTable ($date = '')
    {
        $model = M("moulding_dmd");
    

        switch($_REQUEST["site"]) {
            case 1000:
                $map["site"] = '1000';
                break;
            case 6000:
                $map["site"] = '6000';
                break;
        }
        
        if (empty($date)) {
            // get the closest date as default
            $date = $model->where($map)->max("date");
        }
        $map["date"] = $date;
    
    
        $results = $model->order("shift, no")->where($map)->select();
        $sParts = [];
        foreach ($results as $item) {
            $shift = $item["shift"];
            $sParts[$shift][] = $item;
    
        }
        $shifts = array_unique(array_keys($sParts));
        
        $this->assign("date", $date);
        $this->assign("shifts", $shifts);
        $this->assign("sParts", $sParts);
        $this->display();
    }
    
 
    
}