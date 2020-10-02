<?php


namespace Home\Controller;
use Think\Controller;
use Think\Model;
import("Date.DateHelper");
/**
 * 物料MRP控制器
 * @author wz
 *
 */
class ProDayMrpController extends BaseDayMrpController
{

    protected function getMrpModel()
    {
        if (empty($this->_mrpModel)) {
            $this->_mrpModel = M("prodaymrp");
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
    
    
    public function getFormattedDatesMap ()
    {
        $map = ['drps_date' => ["EGT", $this->getInitStockDate()]];
        if (isset($_REQUEST["site"])) {
            $map["drps_site"] = $_REQUEST["site"];
        }
        $result = M("drps_mstr")->distinct(true)->field("drps_date, drps_type")->where($map)->select();
        
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
//         $pdayToMondayMap = [];
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
        
        $map = [];
        foreach ($dDates as $date) {
            $map[$date] = self::getFormattedDate($date, 'd');
        }
        foreach ($wDates as $date) {
            $map[$date] = self::getFormattedDate($date, 'w');
        }
        foreach ($mDates as $date) {
            $map[$date] = self::getFormattedDate($date, 'm');
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
            $map["comp_isdmrp"] = '0';
        } else {
            // 申购员应能查看所有状态的到货日程才合适。
            //$map["tran_ispass"] = ["neq", '2'];
        }
        
        if (isset($_REQUEST['orderField'])) { 
            $orderField = $_REQUEST['orderField'];
        }
        if ($orderField == '') {
            // 默认按照未审批、已提交、已审核的状态 + 供应商代码进行排序，方便申购员查询和提交时按顺序依次操作。
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
            $vend = $vends[$pageCurrent - 1];
            
            
            // 如果当前页供应商需要进行mrp，执行。
            if ($this->isCompsDayMrpOfVend($vend)) {
                $this->doCompsDayMrpOfVend($vend);
            }
            
            
            // 不要直接使用 _search()返回的可能包含多余筛选的条件，直接使用基础条件。
            $map = $this->getMrpConds();
            $map["comp_vend"] = $vend;
            // 获取用于mrp计算的最近版本（尚未审核或最新已审核）数据 
            $activeNbr = $this->getActiveNbrOfVend($vend);
            $map["tran_nbr"] = $activeNbr ? $activeNbr : ["exp", "is null"];
            
            // 单页显示结果按照零件号排序
            $result = $model->where($map)->order("comp_part")->select();

            $dates = [];
            $parts = self::convertToCompsInfoFromDbResult($result, $dates);
            $parts =  self::accumulateCompsDrpsQtys($parts);
            $parts = self::calculateCompsStock($parts);
            //$parts = self::filterNoneOrderedParts($parts);
            
            $activePassState = reset($parts)["tran_ispass"];
   
            
            $model->commit();
            
            $numPerPage = count($parts);
        
            $dHeadersMap = $this->getFormattedDatesMap();
            
            $this->assign("startDate", min($dates));
            $this->assign("dates", $dates);
            $this->assign("dHeadersMap", $dHeadersMap);
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
                    $this->display("proDayMrp:index-nb");
                    break;
                case 6000:
                    $this->display("proDayMrp:index-cq");
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
                foreach ($_POST["orderQty"] as $key => $dateMap) {
                    list($vd_addr, $comp_part, $comp_site) = array_map("addslashes", explode("-", $key));
                    if (empty($vd_addr) || empty($comp_part) || empty($comp_site)) {
                        continue;
                    }
                
                    // 获取用于mrp计算的最近版本（尚未审核或最新已审核）数据,并保存修改的mrp数据。
                    $activeNbr = $this->getActiveNbrOfVend($vd_addr);
                    foreach ($dateMap as $date => $ord_qty) {
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

            if (isset($_POST["tranQty"])) {
                foreach ($_POST["tranQty"] as $key => $dateMap) {
                    list($vd_addr, $comp_part, $comp_site) = array_map("addslashes", explode("-", $key));
                    if (empty($vd_addr) || empty($comp_part) || empty($comp_site)) {
                        continue;
                    }
            
                    // 获取用于mrp计算的最近版本（尚未审核或最新已审核）数据,并保存修改的mrp数据。
                    $activeNbr = $this->getActiveNbrOfVend($vd_addr);
                    foreach ($dateMap as $date => $tran_qty) {
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
                            $tranModel->tran_qty = ':tran_qty';
                            $tranModel->tran_ispass = ':tran_ispass';
            
                            $bind[':tran_name']  =    array(self::getUserName(),\PDO::PARAM_STR);
                            $bind[':tran_mtime']  =    array($curTime,\PDO::PARAM_STR);
                            $bind[':tran_qty']  =    array($tran_qty,\PDO::PARAM_STR);
                            $tran_ispass = 1;
                            $bind[':tran_ispass']  =    array($tran_ispass,\PDO::PARAM_STR);
                            $tranModel->where($where)->bind($bind)->save();
                        } else {
                            throw new \Exception("no original unnbr data exists, update is forbidden");
                        }
                    }
                }
            }
            
            
            // 将当前vend页的所有日送货子部件的活动版本ispass状态置为1.
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
            $msg = "已提交供应商: $vend 的日采购数据";
        } else {
            $err = true;
            $msg = '非法请求：未提交供应商日采购数据';
        }
    
    
        $data = new \stdClass();
        $data->statusCode = $err ? 300 : 200;
        $data->message = $msg ?  $msg : "供应商日采购数量确认成功";
        $this->ajaxReturn($data);

    }
    
    public function submitAllVendOrders()
    {
        $mrpModel = $this->getMrpModel();
        $map = $this->getMrpConds();
        
        
        $tranModel = $this->getTranModel();
        $tranModel->startTrans();
        
        $vends = $mrpModel->lock(true)->distinct(true)->where($map)->field("comp_vend")->getField("comp_vend", true);

        $curTime = date("Y-m-d H:i:s");
        foreach ($vends as $vend) {
            if (!$this->hasUncheckedNbrOfVend($vend)) {
                continue;
            }
            
            $comps = $this->getCompsOfVend($vend);
            
            $activeNbr = $this->getActiveNbrOfVend($vend);
            $map = [];
            $map["tran_part"] = ["in", $comps];
            $map["tran_vend"] = $vend;
            $map["tran_nbr"] = $activeNbr ? $activeNbr : ["exp", "is null"];
            $tranModel->tran_ispass = 1;
            $tranModel->where($map)->save();
        }
        $tranModel->commit();
 
    }

    
    static protected function convertToCompsInfoFromDbResult($result, array &$availDates = [])
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
        
        
        $pdayToMondayMap = [];
//         // 从所有单日日期中提取预测单日日期
//         $pdayToMondayMap = [];
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

        
        // 为可到货的单日日期使用日期-星期几映射表
        $dateWeekdayMap =  self::getDateWeekdayMap($dDates);
    
    
        //G("begin");
        $compsInfo = [];
        foreach ($result as $row) {
            $uid = $row["comp_vend"] . "-" . $row["comp_part"]. "-" . $row["comp_site"];

            if (!isset($compsInfo[$uid])) {
                $compsInfo[$uid] = $row;
                $compsInfo[$uid]['in_qty_oh'] = round($row['in_qty_oh']);
                //$parts[$uid]["par_ismrp"] = (bool)$row["par_ismrp"];
                $compsInfo[$uid]["tran_ispass"] = intval($row["tran_ispass"]);
                $compsInfo[$uid]["shop_day_calc"] = self::convertBinCalToWeekdayMap($row["shop_day"]); 
                $compsInfo[$uid] += [
                        "pars" => [],
                        "qty_pers" => [],
                        "yld_pcts" => [],
                        "pars_drps" => [],
                        "fday_map" => self::calculateFirstFdayMap(reset($dates), end($dates), $row["vd_fday"])
                ];
            }
            
    
            if (!isset( $compsInfo[$uid]["qty_pers"][$row["par_part"]] ) ) {
                $compsInfo[$uid]["qty_pers"][$row["par_part"]] = floatval($row["ps_qty_per"]);
                $compsInfo[$uid]["yld_pcts"][$row["par_part"]] = floatval($row["par_yld_pct"]);
                $compsInfo[$uid]["pars"][] = $row["par_part"];
            }
            
            $date = $row["drps_date"];
            
            if (!isset($pdayToMondayMap[$date])) {
                // 非预测性单日日期或多日日期的计划数量直接使用
                if (!isset($compsInfo[$uid]["pars_drps"][$date][$row["par_part"]])) {
                    $compsInfo[$uid]["pars_drps"][$date][$row["par_part"]] = floatval($row["drps_qty"]);
                    $compsInfo[$uid]["ismrp"][$date][$row["par_part"]] = (bool)$row["drps_ismrp"];
                }
                 
                if (!isset($compsInfo[$uid]["tran_qtys"][$date])) {
                    $compsInfo[$uid]["tran_qtys"][$date] = floatval($row["tran_qty"]);
                    $compsInfo[$uid]["tran_day_qtys"][$date] = floatval($row["tran_qty"]);
                }
                
                if (!isset($compsInfo[$uid]["shop_qtys"][$date])) {
                    $compsInfo[$uid]["shop_qtys"][$date] = floatval($row["tran_ord_qty"]);
                }
            } else {
                // 预测的单日计划数量作为数组项使用，待后续合并累加至所在周的周一的日计划中
                $monday = $pdayToMondayMap[$date];
                 
                $compsInfo[$uid]["pars_drps"][$monday][$row["par_part"]][$date] = floatval($row["drps_qty"]);
                if ($row["drps_ismrp"]) {
                    // 只要某周的预测日计划有一个已经进行了修改，则该周一即设置mrp标志
                    $compsInfo[$uid]["ismrp"][$monday][$row["par_part"]] = true;
                }
                 
                $compsInfo[$uid]["tran_qtys"][$monday][$date] = floatval($row["tran_qty"]);
                $compsInfo[$uid]["tran_day_qtys"][$monday][$date] = floatval($row["tran_qty"]);
                
                $compsInfo[$uid]["shop_qtys"][$monday][$date] = floatval($row["tran_ord_qty"]);
            }
            
            

            
            
//             if (!isset($compsInfo[$uid]["is_shop_day"][$date])) {
//                 if ($row["comp_ord_per"] <= 7) {
//                     // 对于按日到货，周日期和月日期允许到货，日日期取决于日历表决定是否允许到货。
//                     $wd = $dateWeekdayMap[$date];
                     
//                     if ($row["drps_type"] != 'd') {
//                         // always treat week and month rps dates as shop days
//                         $compsInfo[$uid]["is_shop_day"][$date] = true;
//                     } else {
//                         // determine day rps dates by shop-day-calander
//                         $compsInfo[$uid]["is_shop_day"][$date] = (bool)$compsInfo[$uid]["shop_day_calc"][$wd];
//                     }
//                 } else {
//                     // 对于按周到货，月日期允许到货，周日期和日日期只有周一允许到货，且宁波隔周到货。
//                     if ($row["drps_type"] == 'm') {
//                         // always treat month rps dates as shop days
//                         $compsInfo[$uid]["is_shop_day"][$date] = true;
//                     } else if (self::isMonday($date)) {
//                         switch ($row["comp_site"]) {
//                             case 1000:
//                                 if (self::isOfOddWeek($date)) {
//                                     $compsInfo[$uid]["is_shop_day"][$date] = true;
//                                 } else {
//                                     $compsInfo[$uid]["is_shop_day"][$date] = false;
//                                 }
//                                 break;
//                             case 6000:
//                             default:
//                                 $compsInfo[$uid]["is_shop_day"][$date] = true;
//                                 break;
//                         }
//                     } else {
//                         $compsInfo[$uid]["is_shop_day"][$date] = false;
//                     }
//                 }
                 
//             }

        }
        
        foreach ($compsInfo as &$compInfo) {
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
            
            foreach ($compInfo['shop_qtys'] as &$doq) {
                if (is_array($doq)) {
                    $doq = array_sum($doq);
                }
            }
        }

        return $compsInfo;
    }
    

    
    
    static protected function calculateCompsStock ($compsInfo)
    {
        foreach ($compsInfo as &$compInfo) {
            $compInfo["shop_dates"] = array_keys(array_filter($compInfo["is_shop_day"]));
            $demandDateMap = $transitDateMap = $shopDateMap = $stockDateMap  = [];
            

            // calculate predicted order amount
            $options = [
                    "demandDateMap" => $compInfo["dmnd_qtys"],
                    "transitDateMap" => $compInfo["tran_qtys"],
                    "shopDates" => $compInfo["shop_dates"],
                    "orgStock" => $compInfo["in_qty_oh"],
                    "saftyStock" => $compInfo["comp_rop"],
                    "baseOrderAmountForMultiple" => $compInfo["comp_ord_mult"],
            ];
            $options["orderDateMap"] = $compInfo["shop_qtys"];
            
            $oac = new \Home\Model\OrderAmountCalculatorModel($options);
            $oac->calculateStockData();
            $compInfo["shop_qtys"] = $oac->getOrderDateMap();
            $compInfo["stock_qtys"] = $oac->getStockDateMap($invalidStockStartDate);
            $compInfo["invalid_stock_start_date"] = $invalidStockStartDate;
    
            if ($invalidStockStartDate) {
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
    

    public function importApprovedTrans ($override = false, $site = '')
    {
        set_time_limit(300);
        
        $override = filter_var($_REQUEST["override"],FILTER_VALIDATE_BOOLEAN);
 
        $ptp = D("PtpDet");
        $tran = M("tran_det");
        
        
        $filepath = "./Public/excelData/approvedOrds.xlsx";
        // parse from excel file.
        $rows = $this->xlsin($filepath);
        array_walk_recursive($rows, function(&$val) {
            $val = trim($val);
        });
        
        
        // read the first row as headers.
        $heads = array_shift($rows);
        $allData = [];
        $ordNbr = intval(date("Ymd") . '01');
        $mrpedVends = [];
        foreach ($rows as $row) {
            $site = $row["A"];
            $part = $row["B"];
            if (empty($site) || empty($part)) {
                continue;
            }
            foreach ($row as $col => $cell) {
                if ($col < 'C' || empty($heads[$col])) {
                    continue;
                }
        
                $allData[] = [
                        "tran_site" => $site,
                        "tran_part" => $part,
                        "tran_date" => $heads[$col],
                        "tran_ord_qty" => 0,
                        "tran_qty" => intval($row[$col]),
                        "tran_ispass" => 2,
                        "tran_nbr" => $ordNbr
                ];
            }
        }
    
       
        $tran->startTrans();
        $tran->lock(true);
        
        try {
            if ($override) {
                if ($site) {
                    $tran->where("tran_site=" . $site)->delete();
                } else {
                    $tran->where("1")->delete();
                }
            }
            
            $unum = $inum = 0;
            foreach ($allData as $data) {
                // find the vend
                $ptpCond = [];
                $ptpCond['ptp_site'] = $site;
                $ptpCond['ptp_part'] = $data["tran_part"];
                $vend = $ptp->where($ptpCond)->getField("ptp_vend", true)[0];
            
                if ($vend) {
                    $data["tran_vend"] = $vend;
                    $mrpedVends[] = $vend;
                } else {
                    $warnings[] = "$data[tran_part]无关联供应商";
                    continue;
                }
            
            
            
                $tranCond = [
                        'tran_site' => $data["tran_site"],
                        'tran_part' => $data["tran_part"],
                        'tran_date' => $data["tran_date"],
                ];
                if ($tran->where($tranCond)->count()) {
                    $unum += $tran->where($tranCond)->save($data);
                } else {
                    $lid = $tran->add($data);
                    if ($lid > 0) {
                        $inum++;
                    }
                }
            }
            
            
            // 设置所有相关物料的已mrp标志
            $mrpedVends = array_unique($mrpedVends);
            foreach ($mrpedVends as $vend) {
                $where = $bind = [];
                $where = [
                        "ptp_site" => ":ptp_site",
                        "ptp_vend" => ":ptp_vend"
                        
                ];
                $bind = [
                        ":ptp_site" => array($site,\PDO::PARAM_STR),
                        ":ptp_vend" => array($vend,\PDO::PARAM_STR)
                        
                ];
                $ptp->where($where)->bind($bind)->save([
                        "ptp_isdmrp" => 0
                ]);
            }
            
            $tran->commit();
            echo "已审核采购日程数据导入成功";
        } catch(\Exception $e) {
            $tran->rollback();
            echo "Error: 已审核采购日程数据导入失败<br />";
            echo $e->getMessage();
        }

        
        
        
    }
    
    
    
    public function exportBalanceExcel($vend = '')
    {
        set_time_limit(500);
        $data = [];
        $model = $this->getMrpModel();
    
        $map = self::$baseConds;
        $map = $this->_search();
        
        $dHeadersMap = $this->getFormattedDatesMap();
        
        if ($vend) {
            $vends = [$vend];
        } else {
            $vendAddrsResult = $model->distinct(true)->field("comp_vend")->order("id")->where($map)->select();
            foreach ($vendAddrsResult as $row) {
                $vends[] = $row["comp_vend"];
            }
            sort($vends);
        }
       
        
        $vendsData = [];
        foreach ($vends as $vend) {
            if ($this->isCompsDayMrpOfVend($vend)) {
                $this->doCompsDayMrpOfVend($vend);
            }
            
            $map = $this->getMrpConds();
            $map["comp_vend"] = $vend;
            // 获取用于mrp计算的最近版本（尚未审核或最新已审核）数据
            $activeNbr = $this->getActiveNbrOfVend($vend);
            $map["tran_nbr"] = $activeNbr ? $activeNbr : ["exp", "is null"];
            
            $result = $model->where($map)->order("comp_part")->select();
            

            $dates = [];
            $items = self::convertToCompsInfoFromDbResult($result, $dates);
            $items =  self::accumulateCompsDrpsQtys($items);
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
        $headers = array_merge($headers, $dates);
    
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
            if ($logoEndCell < 'H1') {
                $logoEndCell = 'H1';
            }
            

    
    
            
            // display the balance title
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
            ->setCellValue($logoStartCell, "$vendName-平衡表");
            $logoCellStyle = $objActSheet->getStyle($logoStartCell);
            $logoCellStyle->getFont()->setName("微软雅黑")->setBold(true)->setSize(30)
            ->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_BLUE);
            $logoCellStyle->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    
            $column = 3;
            $objActSheet = $objPHPExcel->getActiveSheet();
            foreach ($items as $key => $part) { // 行写入
                // 用五行来写入一个零件
    
                // 用若干合并的4列来写非日期相关信息：
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
                ->setCellValueExplicit("B" . ($column + 1), "包装: " . floatval($part["comp_ord_mult"]))
                ->setCellValueExplicit("B" . ($column + 2), "安全: " . floatval($part["comp_rop"]))
                ->setCellValueExplicit("B" . ($column + 3), "初始库存: " . floatval($part["in_qty_oh"]))
                ;
    
                $objActSheet
                ->setCellValueExplicit("C$column", "需求")
                ->setCellValueExplicit("C" . ($column + 1), "采购")
                ->setCellValueExplicit("C" . ($column + 2), "在途")
                ->setCellValueExplicit("C" . ($column + 3), "结余")
                ;
    
                // 逐条日期写入数量信息
                $prefix = '';
                $j = 'D';
                foreach ($dates as $date) {
                    $objActSheet
                    ->setCellValueExplicit($prefix . $j . $column, floatval($part["dmnd_qtys"][$date]))
                    ->setCellValueExplicit($prefix . $j . ($column + 1), floatval($part["shop_qtys"][$date]))
                    ->setCellValueExplicit($prefix . $j . ($column + 2), floatval($part["tran_qtys"][$date]))
                    ->setCellValueExplicit($prefix . $j . ($column + 3), floatval($part["stock_qtys"][$date]))
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
    
                $column = $column + 4;
            }
    
    
    
            $sheetIndex++;
            $objPHPExcel->createSheet();
    
        }
    
    
        
    
        $today = date("Y_m_d");
        $ptpBuyer = self::getCurrentBuyer();
        if ($ptpBuyer) {
            $filename = "pod balance table($ptpBuyer-$today).xls";
        } else {
            $filename = "pod balance table($today).xls";
        }
        //$fileName = iconv("utf-8", "gb2312", $fileName);
    
        $objPHPExcel->setActiveSheetIndex(0);
       
        
        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
    
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
    }
   
    public function exportOrderAmountCsv()
    {
    
 
        $list = [];
        
        // write csv title line
        $titleItem = [
                "地点",
                "订单号",
                "供应商",
                "物料代码",
                "到货日期",
                "在途量",
                "采购量",
                "总要求到货量",
        ];
        
        $list[] = $titleItem;
        
        $map = [];
        switch($_REQUEST["site"]) {
            case 1000:
                $map["tran_site"] = '1000';
                break;
            case 6000:
                $map["tran_site"] = '6000';
                break;
        }
        
        $model = M("tran_pod");
        $results = $model->distinct(true)->where($map)->order("tran_site, pod_nbr, tran_part, tran_date")->select();
        
        foreach ($results as $item) {
            $item["tran_real_qty"] = $item["tran_qty"] + $item["tran_ord_qty"];
            $list[] = [
                    $item["tran_site"],
                    $item["pod_nbr"],
                    $item["tran_vend"],
                    $item["tran_part"],
                    $item["tran_date"],
                    floatval($item["tran_qty"]),
                    floatval($item["tran_ord_qty"]),
                    $item["tran_qty"] + $item["tran_ord_qty"]
            ];
        }
    
        $filename = "podSche.csv";
    
        $this->exportCSV($filename, $list);
    }
}