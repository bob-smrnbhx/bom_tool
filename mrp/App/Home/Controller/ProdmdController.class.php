<?php

namespace Home\Controller;
use Think\Controller;
use Think\Model;

class ProdmdController extends ProplanController
{
    protected $_weekdayShiftsRules = [
            1 => 1,
            2 => 1,
            3 => 2,
            4 => 2,
            5 => 1,
            6 => 1,
            7 => 0
    ];
    
    /**
     * 
     * the binary numbers indicating weekday state for different product class, from Sunday to Saturday
     * @var array
     */
    protected $_classWeekdayRules = [
            "A" => 0b0101110,
            "B" => 0b0111101,
            "D" => 0b0011110,
            "E" => 0b0000001,
    ];
    
    protected $_isWeekdayClassMaps = [
            "A" => [],
            "B" => [],
            "D" => [],
            "E" => []
    ];
    
    protected $_shiftAssyCapacity = 640;
    
    /**
     * 每日的剩余可用产能
     * @var array
     */
    protected $_availAssyDayCapacities = [];
    
    
    protected $_shiftCapacityWelding = 1050;
    
    //protected $_maxConfig = 4; // or 2
    protected $_saftyDayLength = 4;
   
    
    protected $_baseCD539Conds = [
            'ptp_pm_code' => 'L',
            'ptp_status'  => 'AC',
            'ptp_site'    => '1000',
            'ptp_desc1'   => ['exp', 'regexp "^CD539C 总成[A-Z]配"']
    ];
    
    protected $_dmdModel;
    
    protected $_assyPartsInfo = [];
    protected $_activeAssyDates = [];
    protected $_latestInDate = '';
    
    protected $_depth = [];
    
    
    protected function getDmdModel ()
    {
        if (empty($this->_dmdModel)) {
            $this->_dmdModel = M('assy_dmd');
        }
        
        return $this->_dmdModel;
    }
    

    
    protected static function getAssyPartClassFromDesc ($desc1)
    {
        if (preg_match('/总成([A-Z])配/i', $desc1, $match)) {
            return strtoupper($match[1]);
        }
    
        return '';
    }
    
    protected static function convertBinToWeekdayMap ($bin)
    {
        return str_split(str_pad(decbin($bin), 7, 0, STR_PAD_LEFT));
    }
    
    protected function getClassIsWeekdayMap ($class)
    {
        if (empty($this->_isWeekdayClassMaps[$class])) {
            $cwMap = self::convertBinToWeekdayMap($this->_classWeekdayRules[$class]);
            $cwMap[7] = $cwMap[0];
            unset($cwMap[0]);
            foreach ($cwMap as &$is) {
                $is = (bool)$is;
            }
            $this->_isWeekdayClassMaps[$class] = $cwMap;
        }

        return $this->_isWeekdayClassMaps[$class];
    }
    
    
    public function _initialize ()
    {
        foreach ($this->_isWeekdayClassMaps as $class => &$isWeekdayMap) {
            $this->getClassIsWeekdayMap($class);
        }

        // 初始化每日最大产能
        foreach ($this->getAssyActiveDates() as $date) {
            $this->_availAssyDayCapacities[$date] = $this->getAssemblyCapacityByDate($date);
        }
        
        // 获取所有装配零件信息
        $this->getAssyPartsInfo();
    }
    
    public function getLatestInDate ()
    {
        if (empty($this->_latestInDate)) {
            $this->_latestInDate = M("ptp_stock")->field("in_date")->where($this->_baseCD539Conds)->max("in_date");
        }
        
        return $this->_latestInDate;
    }
    
    /**
     * 需要获取所有活动的需求日期，从最小到最大，中间任何一个日期都需要包含在内。
     */
    public function getAssyActiveDates ()
    {
        if (empty($this->_activeAssyDates)) {
            $conds = $this->_baseCD539Conds;
            $conds['dmd_date'] = ['EGT', $this->getLatestInDate()];
            $this->_activeAssyDates = $this->getDmdModel()
            ->distinct(true)->field("dmd_date")
            ->where($conds)->getField("dmd_date", true);
            
            $startDate = min($this->_activeAssyDates);
            $endDate = max($this->_activeAssyDates);
            $this->_activeAssyDates = self::getDatesFromRange($startDate, $endDate);
            
        }

        return $this->_activeAssyDates;
    }
    
    protected function getAssyIsWorkdayDateMap ()
    {
        $dates = $this->getAssyActiveDates();
        $map = [];
        foreach (array_keys($this->_classWeekdayRules) as $class) {
            $map[$class] = [];
            foreach ($dates as $date) {
                $map[$class][$date] = $this->isWorkdayByClassAndDate($class, $date);
            }
        }
        
        return $map;
    }
    
    protected function getAssyCapacityDateMap ()
    {
        $dates = $this->getAssyActiveDates();
        $map = [];
        foreach ($dates as $date) {
            $wd = date("N", strtotime($date));
            $map[$date] = $this->_weekdayShiftsRules[$wd] * $this->_shiftAssyCapacity;
        }

        return $map;
    }
    
    protected function getAssyisDoubleShiftDateMap ()
    {
        $dates = $this->getAssyActiveDates();
        $map = [];
        foreach ($dates as $date) {
            $wd = date("N", strtotime($date));
        
            if ($this->_weekdayShiftsRules[$wd] == 2) {
                $map[$date] = true;
            } else {
                $map[$date] = false;
            }
        
        }
        foreach (array_keys($this->_weekdayShiftsRules) as $wd => $shiftNum) {

        }
        
        return $map;
    }
    
    public function getAssyStartDate ()
    {
        return $this->getAssyActiveDates()[0];
    }
    
    public function getAssemblyCapacityByDate ($date)
    {
        $wd = date("N", strtotime($date));
        return $this->_weekdayShiftsRules[$wd] * $this->_shiftAssyCapacity;
    }
    
    public function isWorkdayByClassAndDate ($class, $date)
    {
        $cwMap = $this->getClassIsWeekdayMap($class);
        $wd = date("N", strtotime($date));
        return $cwMap[$wd];
    }
    
    

    
    /**
     * 只获取在最近的库存日期之后的客户需求
     * 
     */
    protected function _search ()
    {
        $conds = $this->_baseCD539Conds;
        $conds['dmd_date'] = ['EGT', $this->getLatestInDate()];

        
        return $conds;
    }
    
    public function getAssyPartsInfo()
    {
        if (empty($this->_assyPartsInfo)) {
            $conds = $this->_search();
            $result = $this->getDmdModel()->where($conds)->order("ptp_desc1")->select();
            
            foreach ($result as $row) {
                $part = $row["ptp_part"];
                if (!isset($this->_assyPartsInfo[$part])) {
                    $class = self::getAssyPartClassFromDesc($row['ptp_desc1']);
                    $this->_assyPartsInfo[$part] = [
                            'part'     => $row["ptp_part"],
                            'site'     => $row["ptp_site"],
                            'desc1'    => $row["ptp_desc1"],
                            'buyer'    => $row["ptp_buyer"],
                            'orgStock' => floatval($row["in_qty_oh"]),
                            'class'    => $class,
                            'dmds'     => [],   // 只保存有需求量的需求日
                            'prods'    => [],   // 每天都保存
                            'stocks'   => []   // 每天都保存
                    ];
                }
            
                // 每个成品的日需求量映射，只保存有需求的日期。
                if (!isset($this->_assyPartsInfo[$part]['dmds'][$row["dmd_date"]]) && $row['dmd_qty'] != 0) {
                    $this->_assyPartsInfo[$part]['dmds'][$row["dmd_date"]] = floatval($row['dmd_qty']);
                }
            }
            
            foreach ($this->_assyPartsInfo as &$partInfo) {
                // 每个零件的日需求量映射，按日期从小到大排序。
                ksort($partInfo['dmds']);
                // 累计每个零件的近期总需求量
                $partInfo["dmdsSum"] = array_sum($partInfo['dmds']);
                // 近期总需求量为0的零件，不参与生产计划安排
                $partInfo["hasDmds"] = !empty($partInfo["dmdsSum"]);
            }
        }
        
        
        return $this->_assyPartsInfo;
    }
    
    protected function getAssyPartsInfoByClass ($classes)
    {
        if (!is_array($classes)) {
            $classes = [$classes];
        }
        $this->getAssyPartsInfo();
        $filteredPartsInfo = [];
        // 务必保持内部数据和返回数据的数组项间的引用关联
        foreach ($classes as $class) {
            foreach ($this->_assyPartsInfo as $part => &$partInfo) {
                if ($partInfo['class'] == $class) {
                    $filteredPartsInfo[$part] = &$partInfo;
                }
            }
        }
       
        return $filteredPartsInfo;
    }

    /**
     * 
     * 获取各零件配置类型的累计'净'需求量映射
     * @param string $fromDate: 可指定从哪个需求日开始进行累计
     * @param int $accuLength: 可指定计算的累计日期长度，默认计算到活动日期末尾
     * @return int[]
     */
    protected function getAssyClassNetAccuDmds ($fromDate = '', $accuLength = 0)
    {
        $accuLength = intval($accuLength);
        if ($accuLength < 1) {
            $accuLength = 9999;
        }
        
        $classNetAccuDmds = [];
        $i = 0;
        
        
        
        $activeDates = self::getAssyActiveDates();
        $classNetAccuDmds = [];
        foreach ($this->_assyPartsInfo as $partInfo) {
            $class = $partInfo["class"];
            $i = 0;
            
            $partDmds = self::getSubArrayFromKey($partInfo["dmds"], $fromDate, $accuLength);
            $curDateIndex = array_search($fromDate, $activeDates);
            if ($curDateIndex == 0) {
                $prevStock = $partInfo["orgStock"];
            } else {
                $prevDate = $activeDates[$curDateIndex - 1];
                $prevStock = $partInfo["stocks"][$prevDate];
            }
            $classNetAccuDmds[$class] += array_sum($partDmds) - $prevStock;

        }
        
        return $classNetAccuDmds;
    }
    
    protected function getAssyClassesByNetAccuDmdsOrder ($fromDate = '', $accuLength = 0)
    {
        $classAccuDmds = $this->getAssyClassNetAccuDmds($fromDate, $accuLength);
        arsort($classAccuDmds);
        return array_keys($classAccuDmds);
    }
    
    public  function test()
    {
        dump($this->getAssyClassNetAccuDmds('2017-04-12', 3));
        dump($this->getAssyClassesByNetAccuDmdsOrder('2017-04-12', 3));
    }
    
    
    public function DoAssemblyMrp ()
    {
        $activeDates = $this->getAssyActiveDates();
        foreach ($activeDates as $curDate) {
            // 每天运算时，都必要先按照各配置从该天起的安全期的总"净"需求量排序，来确定各配置零件的排产的优先级
            $classes = $this->getAssyClassesByNetAccuDmdsOrder($curDate, $this->_saftyDayLength);
            foreach ($this->getAssyPartsInfoByClass($classes) as $part => &$partInfo) {
            //foreach ($this->_assyPartsInfo as $part => &$partInfo) {
                $class = $partInfo["class"];
                // 将该零件的库存数据的最后一项，视为最接近的之前日期的结余数。
                if (count($partInfo["stocks"]) == 0) {
                    // 如果该零件尚未建立任何库存结余结构，直接使用初始库存
                    $preStock = $partInfo["orgStock"];
                } else {
                    $preStock = end($partInfo["stocks"]);
                }
                
                // 当前日期是生产日，且因该零件有总需求(此时才有必要排计划)，才考虑进行生产，并保证满足N天连续需求日覆盖 
                if (self::isWorkdayByClassAndDate($class, $curDate) && $partInfo["hasDmds"]) {
                    $consectiveDmds = self::getSubArrayFromKey($partInfo["dmds"], $curDate, $this->_saftyDayLength);
                    $consectiveDmdsAccu = array_sum($consectiveDmds);
                    
                    $netDmd = $consectiveDmdsAccu - $preStock;
                    // 只要某日的累计净需求量多于上一次的库存结余，就必须安排生产
                    if ($netDmd > 0) {
                        //$prodDate = $this->getClassClosestWorkdayDate($partInfo["class"], $curDate, true); // 如果是非生产日也要确保N天连续需求覆盖，生产只能安排在最接近或等同的'可生产日'（该值很可能与本次需求日日期不同）
                        
                        // 本次需求覆盖所安排的生产量必须累加（而不是直接设置）到'可生产日'的原生产量上去
                        $this->arrangeMinAssyProduction($part, $curDate, $netDmd);
                    }
                }  
                
                // 由于生产日可能一次或多次向前安排，每个零件每个活动天进行计划安排后都必须重新计算之前每天的库存结余。
                $prevdate = '';
                foreach ($activeDates as $date) {
                    if ($date > $curDate) {
                        break;
                    }

                    if (empty($prevdate)) {
                        $pstock = $partInfo["orgStock"];
                    } else {
                        $pstock = $partInfo["stocks"][$prevdate];
                    }
                    
                    $partInfo["stocks"][$date] = $pstock + $partInfo["prods"][$date] - $partInfo["dmds"][$date];
                    
                    $prevdate = $date;
                }
            }
        }
        
        // 在进行每日最小生产保证量计算后，统计出每日生产零件的所有配置类型。
        $dateClassMap = [];
        foreach ($activeDates as $date) {
            foreach ($this->_assyPartsInfo as $part => $partInfo) {
                $class = $partInfo["class"];
                if ($partInfo["prods"][$date]) {
                    $dateClassMap[$date][] = $class;
                }
            }
            $dateClassMap[$date] = array_unique($dateClassMap[$date]);
            
            //echo "prod date: $date with: " . implode(",", $dateClassMap[$date]) . "<br />";
        }
        
        // 统计各配置类型的所有需求日总生产量，按照总量进行排序，安排生产优先级。
        
        
        // 根据涉每日生产零件配置类型，将每日产能排满
        
    }
    

    
    protected function arrangeMinAssyProduction($part, $prodDate, $curNetDmd)
    {
        if ($this->_availAssyDayCapacities[$prodDate] >= $curNetDmd) {
            // 如果'生产日'剩余产能大于等于该零件的当前净需求，可以安排满足全额净需求的生产
            $this->_assyPartsInfo[$part]["prods"][$prodDate] += $curNetDmd;
        
            // 对对应“生产日”进行剩余产能扣减
            $this->_availAssyDayCapacities[$prodDate] -= $curNetDmd;
        } else {  
            // 如果'生产日'剩余产能已经无法满足该零件的净需求，只能安排将剩余产能全部投入生产(前提是剩余产能仍然大于0)
            $leftCapacity = $this->_availAssyDayCapacities[$prodDate];
            if ($leftCapacity > 0) {
                $this->_assyPartsInfo[$part]["prods"][$prodDate] += $leftCapacity;
            }
        
            // 此时对应“生产日”剩余产能将耗尽
            $this->_availAssyDayCapacities[$prodDate] = 0;
        
            // 同时，还需将不足的需求部分，移到前一个"可生产日"进行生产(该步骤可递归进行)
            $curNetDmd -= $leftCapacity;
            $preProdDate = $this->getClassClosestWorkdayDate($this->_assyPartsInfo[$part]["class"], $prodDate, false);
            $this->arrangeMinAssyProduction($part, $preProdDate, $curNetDmd);
        }
    }
    
    public  function getLastElementByArrayKey ($arr, $key)
    {
        if (!array_key_exists($key, $arr)) {
            return false;
        }
        
        $lastKey = false;
        foreach ($arr as $curKey => $val) {
            if ($curKey == $key) {
                break;
            }
            $lastKey = $curKey;
        }
        
        return $lastKey;
    }
    
    public static function getSubArrayFromKey($arr, $startKey, $len)
    {
        if (!array_key_exists($startKey, $arr)) {
            return false;
        }
        
        $subArr = [];
        $inSub = false;
        foreach ($arr as $key => $val) {
            if ($len == 0)  {
                break;
            }
            if ($startKey == $key) {
                $inSub = true;

            }
            
            if ($inSub) {
                $subArr[$key] = $val;
                $len--;
            }
        }
        
        return $subArr;
    }
    
    public function getClassClosestWorkdayDate($class, $date, $allowCurrent = true)
    {
        $wdMap = $this->getClassIsWeekdayMap($class);
        $curWd = date("N", strtotime($date));
        
        if ($wdMap[$curWd] && $allowCurrent) {
            return $date;
        }
        
        $D = new \DateTime($date);
        do {
            $D->sub(new \DateInterval('P1D'));
            $d = $D->format("Y-m-d");
        } while (!self::isWorkdayByClassAndDate($class, $d));
        
        return $d;
    }
    
    
    public function test2()
    {
        dump($this->getAssyActiveDates());
    }
    
    
    public function index ()
    {
        $this->DoAssemblyMrp();
        

        $this->assign("dates", $this->getAssyActiveDates());
        $this->assign("isDoubleShiftDateMap", $this->getAssyisDoubleShiftDateMap());
        $this->assign("capacityDateMap", $this->getAssyCapacityDateMap());
        $this->assign("partsInfo", $this->_assyPartsInfo);
        $this->assign("isWorkdayDateMap", $this->getAssyIsWorkdayDateMap());
        $this->assign("unusedCapacities", $this->_availAssyDayCapacities);
        
        
        $this->display();
    }
    
    public function updatePlans()
    {
        $plans = [];
        
        foreach ($_REQUEST as $key => $val) { 
            list($rtype, $rpart, $rsite, $rdate) = explode("#", $key);
            $rpart=str_replace('_', '.', $rpart);
            
            $plans[] = [
                    "drps_part" => $rpart,
                    "drps_site" => $rsite,
                    "drps_date" => $rdate,
                    "drps_qty" => floatval($val)
            ];
        }
        

        
        $err = false;
        $msg = '';
        try {
            $drp = M("drps_mstr");
            $drp->startTrans();
            

            foreach ($plans as $plan) {
                $where = $bind = [];
                $where['drps_part'] = ':drps_part';
                $where['drps_site'] = ':drps_site';
                $where['drps_date'] = ':drps_date';
 
                $bind[':drps_part']    =  array($plan["drps_part"],\PDO::PARAM_STR);
                $bind[':drps_site']    =  array($plan["drps_site"],\PDO::PARAM_STR);
                $bind[':drps_date']    =  array($plan["drps_date"],\PDO::PARAM_STR);
                
                if ($drp->where($where)->bind($bind)->count() != 0) {
                    $drp->where($where)->bind($bind)->save($plan);
                } else {
                    $drp->add($plan);
                }
                
            }
            
 
            
            $msg = "生产计划更新成功";
            $drp->commit();
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
    
    static function convertToPlansFromDbResult($result)
    {
        $parts = [];
        foreach ($result as $item) {
            $uid = $item["ptp_part"] . $item["ptp_site"];
            if (!isset($parts[$uid])) {
                $parts[$uid] = $item;
            }
            if (!isset($parts[$uid]["dates"][$item["dmd_date"]])) {
                $parts[$uid]["dates"][$item["dmd_date"]] = [
                        'dmd_qty' => floatval($item["dmd_qty"]),
                        'drps_qty' => floatval($item["drps_qty"]),
                        'drps_id' => $item["drps_id"],
                ];
            }
    
    
 
    
        }
         
        // sort by drp_date, wrp_week, mrp_month;
        foreach ($parts as &$rpart) {
            isset($rpart["dates"]) && ksort($rpart["dates"]);
        }
    
        return $parts;
    }
    
    static function calculateProductStocks ($parts)
    {
        foreach ($parts as &$partInfo) {
            $prevStock = $partInfo["in_qty_oh"];
            foreach ($partInfo["dates"] as $date => &$item) {
                $item["stock_qty"] = $prevStock + $item["drps_qty"] - $item["dmd_qty"];
                
                $prevStock = $item["stock_qty"];
            }
    
        }
        
        return $parts;
    }
    
    
    /**
     * @throws \Exception
     * @return string
     */
    protected function getUploadedDmdsFile ()
    {
        $upload = new \Think\Upload();
        $upload->maxSize   =     30000000 ;
        $upload->exts      =     array('xls', 'xlsx');
        $upload->rootPath  =     './Uploads/dmds/';
        $upload->autoSub = true;
        $upload->subName = array('date','Ymd');
        $upload->saveName = 'dmds_' . time().'_'.mt_rand();;
    
        $info = $upload->upload();
        if(!$info) {
            throw new \Exception($upload->getError());
        } else {
            if (count($info) > 1) {
                throw new \Exception("错误：一次只允许上传一个客户需求文件");
            }
            $file = current($info);
            return $upload->rootPath . $file['savepath'].$file['savename'];
        }
    }
    
    public function importDmds ()
    {
        set_time_limit(300);
        $override = filter_var($_REQUEST["override"],FILTER_VALIDATE_BOOLEAN);
        
        $start = time();
        $err = false;
        $msg = '';
        try {
            $filepath = $this->getUploadedRpsFile();
            $rows = $this->xlsin($filepath, 0);
            array_walk_recursive($rows, function(&$val) {
                $val = trim($val);
            });
            // read the first row as header.
            $heads = array_shift($rows);
            
            $dmdDateCellMap = [];
            foreach ($heads as $key => $head) {
                // 将从B列开始的所有列解析为日期
                if ($key >= "B") {
                    $dmdDateCellMap[$key] = $head;
                }
            }
            
            
            $allData = [];
            foreach ($rows as $row) {
                $part = $row["A"];
                foreach ($dmdDateCellMap as $rowId => $date) {
                    $allData[] = [
                            "dmd_site" => 1000,
                            "dmd_part" => $part,
                            "dmd_date" => $date,
                            "dmd_qty" => floatval($row[$rowId])
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
                $msg .= "如下零件号在物料主数据表中不存在： <br />" . implode(", ", $nonMatchParts);
            }
            $this->success($msg, '', 30);
        } catch (\Exception $e) {
            $dmd->rollback();
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
    
    
    
    static function getDatesFromRange($startdate, $enddate){
    
        $stimestamp = strtotime($startdate);
        $etimestamp = strtotime($enddate);
    
        // 计算日期段内有多少天
        $days = ($etimestamp-$stimestamp)/86400+1;
    
        $dates = array();
        for($i = 0; $i < $days; $i++){
            $dates[] = date('Y-m-d', $stimestamp + (86400 * $i));
        }
    
        return $dates;
    }
}