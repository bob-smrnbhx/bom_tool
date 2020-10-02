<?php

namespace Home\Controller;
use Think\Controller;
use Think\Model;

class ProdmdAssyNBController extends ProplanController
{
    private $_weekdayShiftsRules = [
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
     * the binary numbers indicating weekday work state for different product class, from Sunday to Saturday
     * @var array
     */
    private $_classWeekdayWorkRules = [];
    
    private $_shiftAssyCapacity;
    
    //protected $_maxConfig = 4; // or 2
    private $_saftyDayLength = 4;
    
    protected $_isWeekdayWorkableClassMaps = [
];
    protected $_isWorkdayDateMap = [];
    
    protected $_dateTypeMap = [];

    

    
    /**
     * 每日的剩余可用产能跟踪数组
     * @var array
     */
    protected $_availAssyDayCapacities = [];
    
    
    /**
     * @var integer
     */
    protected $_shiftCapacityWelding ;
    
    

    
    private $_baseCommonConds = [
            'ptp_pm_code' => 'L',
            'ptp_status'  => 'AC'
    ];
    
    private $_baseCD539Conds = [
            'ptp_pm_code' => 'L',
            'ptp_status'  => 'AC',
            'ptp_site'    => '1000',            
            'lnd_line'    => 'A00016'
    ];
    
    private $_baseC490Conds = [
            'ptp_pm_code' => 'L',
            'ptp_status'  => 'AC',
            'ptp_site'    => '6000',
            'lnd_line'    => 'A60006'
    ];
    
    private $_baseCD391Conds = [
            'ptp_pm_code' => 'L',
            'ptp_status'  => 'AC',
            'ptp_site'    => '6000',
            'lnd_line'    => 'A60002'
    ];
    
    private $_baseB515Conds = [
            'ptp_pm_code' => 'L',
            'ptp_status'  => 'AC',
            'ptp_site'    => '6000',
            'lnd_line'    => 'A60001'
    ];
    
    private $_baseB315AConds = [
            'ptp_pm_code' => 'L',
            'ptp_status'  => 'AC',
            'ptp_site'    => '6000',
            'lnd_line'    => 'A60005'
    ];
    
    protected $_baseProjectConds = [];
    
    protected $_dmdModel;
    
    protected $_assyPartsInfo = [];
    
    protected $_activeAssyDates = [];
    protected $_activeAssyDayDates = [];
    protected $_activeAssyWeekDates = [];
    protected $_activeAssyMonthDates = [];
    protected $_daysLength, $_weeksLength, $_monthsLength;
    
    protected $_today = '';
    
    protected $_depth = [];
    
    
    protected function getDmdModel ()
    {
        if (empty($this->_dmdModel)) {
            $this->_dmdModel = M('assy_dmd');
        }
        
        return $this->_dmdModel;
    }
    
    protected function getBaseConds ()
    {
        return $this->_baseProjectConds;
        
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
    
    protected function getClassIsWeekdayWorkableMap ($class)
    {
        if (empty($this->_isWeekdayWorkableClassMaps[$class])) {
            $cwMap = self::convertBinToWeekdayMap($this->_classWeekdayWorkRules[$class]);
            $cwMap[7] = $cwMap[0];
            unset($cwMap[0]);
            foreach ($cwMap as &$is) {
                $is = (bool)$is;
            }
            $this->_isWeekdayWorkableClassMaps[$class] = $cwMap;
        }

        return $this->_isWeekdayWorkableClassMaps[$class];
    }
    
    
    public function _initialize ()
    {
        $this->_today = date("Y-m-d", time());
        $this->_today = '2017-04-20';
        
        
        switch ($_REQUEST["project"]) {
            case "cd539" :
                $this->_baseProjectConds = $this->_baseCD539Conds;
                $this->_shiftAssyCapacity = 640;
                $this->_classWeekdayWorkRules = [
                        "A" => 0b0101110,
                        "B" => 0b0111101,
                        "D" => 0b0011110,
                        "E" => 0b0000001,
                ];
                break;            
            default:
                $this->_baseProjectConds =  $this->_baseCommonConds;
        }
        
        foreach ($this->_isWeekdayWorkableClassMaps as $class => &$isWeekdayMap) {
            $this->getClassIsWeekdayWorkableMap($class);
        }

        // 初始化每日最大产能
        foreach ($this->getAssyActiveDates() as $date) {
            if ($this->isDayDate($date)) {
                $this->_availAssyDayCapacities[$date] = $this->getAssemblyCapacityByDate($date);
            }
        }
        
        // 获取所有装配零件信息， 该操作逻辑上必须放在计算并保存活动日期之后
        $this->getAssyPartsInfo();
    }
    
    public function getStartActiveDate ()
    {
        return $this->_today;
    }
    
    
    /**
     * 需要获取所有活动的需求日期，从最小到最大，中间任何一个可用日期都需要包含在内。
     */
    public function getAssyActiveDates ()
    {
        if (empty($this->_activeAssyDates)) {
            $conds = $this->getBaseConds();
            $conds['dmd_date'] = ['EGT', $this->getStartActiveDate()];
            
            $result = $this->getDmdModel()
            ->distinct(true)->field("dmd_date, dmd_type")
            ->where($conds)->select();
            $ddates = $wdates = $mdates = [];
            foreach ($result as $item) {
                switch ($item["dmd_type"]) {
                    case 'd':
                        $ddates[] = $item["dmd_date"];
                        break;
                    case 'w':
                        $wdates[] = $item["dmd_date"];
                        break;
                    case 'm':
                        $mdates[] = $item["dmd_date"];
                        break;
                }
            }
            

            
            // 日类型活动日范围强制从今天开始，到最大日类型需求日结束
            $this->_activeAssyDayDates = self::getDatesBetween($this->_today, max($ddates));
            foreach ($this->_activeAssyDayDates as $date) {
                $this->_dateTypeMap[$date] =  'd';
            }
            $this->_daysLength =  count($this->_activeAssyDayDates);
            
            if ($wdates) {
                $this->_activeAssyWeekDates = self::getMondaysBetween(min($wdates), max($wdates));
                foreach ($this->_activeAssyWeekDates as $date) {
                    $this->_dateTypeMap[$date] =  'w';
                }
                $this->_weeksLength = count($this->_activeAssyWeekDates);
            }

            
            if ($mdates) {
                $this->_activeAssyMonthDates = self::getFirstMonthDayBetween(min($mdates), max($mdates));
                foreach ($this->_activeAssyMonthDates as $date) {
                    $this->_dateTypeMap[$date] =  'm';
                }
                $this->_monthsLength = count($this->_activeAssyMonthDates);
            }

            
            // 将月日期分解为4个周日期
            foreach ($this->_activeAssyMonthDates as $mdate)  {
                // to be continued.....
            }
            
            $this->_activeAssyDates = array_merge($this->_activeAssyDayDates, $this->_activeAssyWeekDates, $this->_activeAssyMonthDates);
            

        }
        

        
        return $this->_activeAssyDates;
    }
    
    protected function isDayDate ($date)
    {
        return $this->_dateTypeMap[$date] == 'd';
    }
    
    protected function getAssyIsWorkdayDateMap ()
    {
        $dates = $this->getAssyActiveDates();
        $map = [];
        foreach (array_keys($this->_classWeekdayWorkRules) as $class) {
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
            if ($this->isDayDate($date)) {
                $wd = date("N", strtotime($date));
                $map[$date] = $this->_weekdayShiftsRules[$wd] * $this->_shiftAssyCapacity;
            }
        }

        return $map;
    }
    
    protected function getAssyIsPeriodDateMap ()
    {
        $dates = $this->getAssyActiveDates();
        $map = [];
        foreach ($dates as $date) {
            if (!$this->isDayDate($date)) {
                $map[$date] = true;
            } else {
                $map[$date] = false;
            }
        
        }

        return $map;
    }
    
    protected function getAssyIsDoubleShiftDateMap ()
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
        if (!$this->isDayDate($date)) {
            // 非日类型日期始终允许为工作日
            return true;
        }
        
        $cwMap = $this->getClassIsWeekdayWorkableMap($class);
        $wd = date("N", strtotime($date));
        return $cwMap[$wd];
    }
    
    

    
    /**
     * 只获取在最近的库存日期之后的客户需求
     * 
     */
    protected function _search ()
    {
        $conds = $this->getBaseConds();
        $conds['dmd_date'] = ['EGT', self::getDateBefore($this->getStartActiveDate())];
        //$conds['dmd_type'] = 'd';

        
        return $conds;
    }
    
    public function getAssyPartsInfo()
    {
        if (empty($this->_assyPartsInfo)) {
            $conds = $this->_search();
            $result = $this->getDmdModel()->where($conds)->order("ptp_desc1")->select();
            
            $activeDates = $this->getAssyActiveDates();
            $dateBeforeActive = self::getDateBefore(reset($activeDates));
            
            foreach ($result as $row) {
                $part = $row["ptp_part"];
                if (!isset($this->_assyPartsInfo[$part])) {
                    $class = self::getAssyPartClassFromDesc($row['ptp_desc1']);
                    $this->_assyPartsInfo[$part] = [
                            'part'     => $row["ptp_part"],
                            'site'     => $row["ptp_site"],
                            'desc1'    => $row["ptp_desc1"],
                            'buyer'    => $row["ptp_buyer"],
                            'line'     => $row["lnd_line"],
                            'mfgLead'  => $row["ptp_mfg_lead"],
                            'class'    => $class,
                            'dmdTypes' => [],
                            'dmds'     => [],    // 只保存有需求量的需求日
                            'prods'    => [],    // 每天都保存
                            'innerStocks' => [], // 每天都保存
                            'outerStocks' => [], // 每天都保存
                            'stocks'   => [],     // 每天都保存
                            'consectAccuDmds' => []
                    ];
                }
                

                
                if (!isset($this->_assyPartsInfo[$part]["orgInnerStock"]) && $row["in_type"] == 'i') {
                    $this->_assyPartsInfo[$part]["orgInnerStock"] = $row["in_qty_oh"];
                }
                
                if (!isset($this->_assyPartsInfo[$part]["orgOuterStock"]) && $row["in_type"] == 'o') {
                    $this->_assyPartsInfo[$part]["orgOuterStock"] = $row["in_qty_oh"];
                }
                
                if ($row["dmd_date"] > $dateBeforeActive) {
                    // 每个成品的日需求量映射，只保存有需求的日期。
                    if (!isset($this->_assyPartsInfo[$part]['dmds'][$row["dmd_date"]]) && $row['dmd_qty'] != 0) {
                        $this->_assyPartsInfo[$part]['dmds'][$row["dmd_date"]] = floatval($row['dmd_qty']);
                        $this->_assyPartsInfo[$part]['dmdTypes'][$row["dmd_date"]] = $row["dmd_type"];
                    }
                } else {
                    if (!isset($this->_assyPartsInfo[$part]["orgDmd"])) {
                        $this->_assyPartsInfo[$part]["orgDmd"] = $row["dmd_qty"];
                    }
                }
            }
            


            foreach ($this->_assyPartsInfo as $part => $partInfo) {
                // 每个零件的日需求量映射，按日期从小到大排序。
                ksort($this->_assyPartsInfo[$part]['dmds']);
            
                // 累计每个零件的日需求总需求量
                $this->_assyPartsInfo[$part]["dmdsSum"] = 0;
                foreach ($this->_assyPartsInfo[$part]['dmds'] as $date => $qty) {
                    if ($this->isDayDate($date)) {
                        $this->_assyPartsInfo[$part]["dmdsSum"] += $qty;
                    }
                }
            
                // 日总需求量为0的零件，不参与生产计划安排
                $this->_assyPartsInfo[$part]["hasDayDmds"] = !empty($this->_assyPartsInfo[$part]["dmdsSum"]);
                
                foreach ($activeDates as $date) {
                    // 获取每个零件每日的累计覆盖日需求量
                    $this->getConsectAccuDayDmdFrom($part, $date);
                }
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
            foreach ($this->_assyPartsInfo as $part => $partInfo) {
                if ($this->_assyPartsInfo[$part]['class'] == $class) {
                    $filteredPartsInfo[$part] = $this->_assyPartsInfo[$part]['class'];
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
            
            $curDateIndex = array_search($fromDate, $activeDates);
            // 只考虑日类型需求作为凭据
            if ($curDateIndex + $accuLength > $this->_daysLength) {
                $accuLength =  $this->_daysLength - $curDateIndex;
            }



            
            if ($curDateIndex == 0) {
                $prevStock = $partInfo["orgOuterStock"] + $partInfo["orgInnerStock"];
                $prevDmd = $partInfo["orgDmd"];
            } else {
                $prevDate = $activeDates[$curDateIndex - 1];
                $prevStock = $partInfo["stocks"][$prevDate];
                $prevDmd = $partInfo["dmds"][$prevDate];
            }
            
            $partDmds = self::getSubArrayFromKey($partInfo["dmds"], $fromDate, $accuLength);
            foreach ($partDmds as $date => $qty)  {
                if ($this->isDayDate($date)) {
                    
                }
            }
            
            $classNetAccuDmds[$class] += array_sum($partDmds) - ($prevStock - $prevDmd);

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
        //dump($this->getAssyClassNetAccuDmds('2017-04-20', 4));
        //dump($this->getAssyClassesByNetAccuDmdsOrder('2017-04-20', 4));
        dump($this->getAssyPartsByProdPriority('2017-04-20', 4));
    }
    
    public function getAssyPartsByProdPriority ($fromDate = '', $accuLength = 0)
    {
        //先根据区间的累计净需求按照从大到小排序
        $accuLength = intval($accuLength);
        if ($accuLength < 1) {
            $accuLength = 9999;
        }
        
 
        $partAccuDmdMap = [];
        $activeDates = self::getAssyActiveDates();
        foreach ($this->_assyPartsInfo as $partInfo) {
            $part = $partInfo["part"];
            $curDateIndex = array_search($fromDate, $activeDates);
            // 只考虑日类型需求作为凭据
            if ($curDateIndex + $accuLength > $this->_daysLength) {
                $accuLength =  $this->_daysLength - $curDateIndex;
            }
        

        
            if ($curDateIndex == 0) {
                $prevStock = $partInfo["orgOuterStock"] + $partInfo["orgInnerStock"];
                $prevDmd = $partInfo["orgDmd"];
            } else {
                $prevDate = $activeDates[$curDateIndex - 1];
                $prevStock = $partInfo["stocks"][$prevDate];
                $prevDmd = $partInfo["dmds"][$prevDate];
            }
            
            $partDmds = self::getSubArrayFromKey($partInfo["dmds"], $fromDate, $accuLength);
            
            $dmdSum = 0;
            foreach ($partDmds as $date => $qty)  {
                if ($this->isDayDate($date)) {
                    $dmdSum += $qty;
                }
            }
            
            $partAccuDmdMap[$part] = $dmdSum - ($prevStock - $prevDmd);

        }
        
        arsort($partAccuDmdMap);
        
        return array_keys($partAccuDmdMap);
    }
    
    protected function hasDayDmdsFrom($part, $date) 
    {
        foreach ($this->_assyPartsInfo[$part]['dmds'] as $curDate => $qty) {
            if ($this->isDayDate($date)) {
                if ($curDate >= $date && $qty != 0) {
                    return true;
                }
            } else {
                break;
            }
 
        }
        
        return false;
    }
    
    /**
     * 根据安全库存日期长度计算从某日起的累计日需求量
     * @param unknown $part
     * @param unknown $fromDate
     * @return void|mixed
     */
    protected function getConsectAccuDayDmdFrom ($part, $fromDate)
    {
        if (!$this->isDayDate($fromDate)) {
            // 如果是非日类型日期，返回null
            return;
        }
        
        if (!isset($this->_assyPartsInfo[$part]["consectAccuDmds"][$fromDate])) {
            // 根据安全库存日期长度计算连续累计库存需求量
            $consectiveDmds = self::getSubArrayFromKey($this->_assyPartsInfo[$part]["dmds"], $fromDate, $this->_saftyDayLength);
            $consectiveAccuDayDmds = 0;
            foreach ($consectiveDmds as $date => $dmdQty) {
                // 只考虑日需求类型的需求量
                if ($this->isDayDate($date)) {
                    $consectiveAccuDayDmds += $dmdQty;
                }
            
            }
            $this->_assyPartsInfo[$part]["consectAccuDmds"][$fromDate] = $consectiveAccuDayDmds;
        }
        
        return  $this->_assyPartsInfo[$part]["consectAccuDmds"][$fromDate];
    }
    
    protected function getDayDmdForMfgLead ($part, $date)
    {
        
    }
    
    /**
     * 根据初步最小生产量计算后的结果，获取某日的产能补充作用的零件的优先级顺序。
     * 对某一日的所有 <可生产的> 并且 <从该日期起还有后续日需求的> 零件号进行排序，排序规则依次：
     * 已安排生产的，统一在未安排生产的之前；
     * 库存量小的，在库存量大的之前
     * @param unknown $date
     */
    public function getAssyPartsComplementPriorityByDate ($date)
    {
        $partStockMap = [];
        $produedPartStockMap = $notProducedPartStockMap = [];
        foreach ($this->_assyPartsInfo as $partInfo) {
            $class = $partInfo["class"];
            $part = $partInfo["part"];
            if (self::isWorkdayByClassAndDate($class, $date) && $this->hasDayDmdsFrom($part, $date)) {
                $stock = $partInfo["stocks"][$date];
                if ($partInfo["prods"][$date]) {
                    $produedPartStockMap[$part] = $stock;
                } else {
                    $notProducedPartStockMap[$part] = $stock;
                }
            }
        }
        asort($produedPartStockMap);
        asort($notProducedPartStockMap);
        //var_dump($date, $produedPartStockMap, $notProducedPartStockMap);
        return array_merge(array_keys($produedPartStockMap), array_keys($notProducedPartStockMap));
    }
    
    public function DoAssemblyMrp ()
    {
        $activeDates = $this->getAssyActiveDates();
        foreach ($activeDates as $curDate) {
            // 只对日类型的需求进行连续需求覆盖运算
            if ($this->isDayDate($curDate)) {
                // 每天运算时，按照当前需求日起的覆盖库存需求数，来确定各配置零件的排产的优先级
                $parts = $this->getAssyPartsByProdPriority($curDate, $this->_saftyDayLength);
                foreach ($parts as $part) {
                    //foreach ($this->_assyPartsInfo as $part => &$partInfo) {
                    $class = $this->_assyPartsInfo[$part]["class"];
                    if (count($this->_assyPartsInfo[$part]["stocks"]) == 0) {
                        // 如果该零件尚未建立任何库存结余结构，直接使用初始库存和前日需求
                        $preOverallStock = $this->_assyPartsInfo[$part]["orgInnerStock"] + $this->_assyPartsInfo[$part]["orgOuterStock"] ;
                        $prevDmd = $this->_assyPartsInfo[$part]["orgDmd"];
                    } else { 
                        // 将该零件的库存数据和需求数据的最后一项，视为前日的库存结余数和前日需求。
                        $prevDate = self::getDateBefore($curDate);
                        $preOverallStock = $this->_assyPartsInfo[$part]["stocks"][$prevDate];
                        $prevDmd = $this->_assyPartsInfo[$part]["dmds"][$prevDate];
                    }
                    
                    // 前日可用于计算的结余数，还必须减去前日的需求数！！！
                    $preOverallStock -= $prevDmd;
                
                    // 根据安全库存日期长度计算累计库存需求量
                    $consectiveDmdsAccu = $this->getConsectAccuDayDmdFrom($part, $curDate);
                    
                    // 当前日期是生产日，且该零件有总需求(此时才有必要排计划)，才考虑进行生产，并保证外库满足N天连续需求日覆盖
                    if (self::isWorkdayByClassAndDate($class, $curDate) && $this->_assyPartsInfo[$part]["hasDayDmds"]) {
                        $netDmd = $consectiveDmdsAccu - $preOverallStock;
//                         if ($part == '04.02.33.0453') {
//                             var_dump($curDate, $consectiveDmdsAccu, $preOverallStock);
//                         }

                        // 只要某日的累计净需求量多于上一次的库存结余，就必须安排生产
                        if ($netDmd > 0) {
                            //$prodDate = $this->getClassClosestWorkdayDate($this->_assyPartsInfo[part]["class"], $curDate, true); // 如果是非生产日也要确保N天连续需求覆盖，生产只能安排在最接近或等同的'可生产日'（该值很可能与本次需求日日期不同）
                
                            // 本次需求覆盖所安排的生产量必须累加（而不是直接设置）到'可生产日'的原生产量上去
                            $this->arrangeMinAssyProduction($part, $curDate, $netDmd);
                        }
                    }

                    
                    
                    // 由于生产日可能一次或多次向前安排，每个零件每个活动天进行计划安排后都必须重新计算之前每天包括当天的库存结余
                    $curIndex = array_keys($activeDates, $curDate)[0];
                    $pastDates = array_slice($activeDates, 0, $curIndex + 1);
                    $this->calculatePartStocksBetween($part, $pastDates);

                }
                
                
                
                
                
//                 // 在进行每日最小生产保证量计算后，统计出每日生产零件的所有配置类型。
//                 $dateClassMap = [];
//                 foreach ($activeDates as $date) {
//                     foreach ($this->_assyPartsInfo as $part => $partInfo) {
//                         $class = $partInfo["class"];
//                         if ($partInfo["prods"][$date]) {
//                             $dateClassMap[$date][] = $class;
//                         }
//                     }
//                     $dateClassMap[$date] = array_unique($dateClassMap[$date]);
                
//                     //echo "prod date: $date with: " . implode(",", $dateClassMap[$date]) . "<br />";
//                 }
                
//                 // 统计各配置类型的所有需求日总生产量，按照总量进行排序，安排生产优先级。
                
                


                
                
            }  else {
                // 对于周类型需求，提前N+M天生产即可。
            }
            
        }
        

        
        // 在安排完每日最小计划生产量后，再逐日进行产能补充（在某日有剩余产能的时候）。
        foreach ($this->_activeAssyDayDates as $curDate) {
            // 根据涉每日生产零件配置类型，将每日产能排满
            if ($this->_availAssyDayCapacities[$curDate]) {
                // 按照指定的规则，提取出待补充产能的优先级最高的最多10个零件，进行产能补充
                $partsForComplement = array_slice($this->getAssyPartsComplementPriorityByDate($curDate), 0, 10);
                $per = floor($this->_availAssyDayCapacities[$curDate] / count($partsForComplement)) ; 
                $extra = $this->_availAssyDayCapacities[$curDate] % count($partsForComplement);
                

                foreach ($partsForComplement as $part) {
                    $this->_assyPartsInfo[$part]["prods"][$curDate] += $per;
                }
                $this->_assyPartsInfo[$part]["prods"][$curDate] += $extra;
                
                $this->_availAssyDayCapacities[$curDate] = 0;
                
                // 逐日补充产能后，重新计算之后活动日的库存结余（全部活动日重新计算也可以），以刷新该日库存结余数，
                // 以让补充过产能的零件，在下次优先级排序中，优先级能下降
                foreach ($this->_assyPartsInfo as $part => $partInfo) {
                    $this->calculatePartStocksBetween($part, $this->_activeAssyDayDates);
                }
            }
        }
        

    }
    
    
    protected function calculatePartStocksBetween ($part, array $dates)
    {
        sort($dates);
        $startDate = reset($dates);
        
        $activeDates = $this->getAssyActiveDates();
        $startActiveDate = reset($activeDates);
        
        $prevdate = '';
        foreach ($dates as $date)  {
            if ($date == $startActiveDate) {
                $pInnerstock = $this->_assyPartsInfo[$part]["orgInnerStock"];
                $pOuterstock = $this->_assyPartsInfo[$part]["orgOuterStock"];
                $pDmd = $this->_assyPartsInfo[$part]["orgDmd"];
                $pOverallStock = $pInnerstock + $pOuterstock - $pDmd;
            } else {
                $pInnerstock = $this->_assyPartsInfo[$part]["innerStocks"][$prevdate];
                $pOuterstock = $this->_assyPartsInfo[$part]["outerStocks"][$prevdate];
                $pDmd = $this->_assyPartsInfo[$part]["dmds"][$prevdate];
                $pOverallStock = $this->_assyPartsInfo[$part]["stocks"][$prevdate] - $pDmd;
            }

            $this->_assyPartsInfo[$part]["stocks"][$date] = $pOverallStock + $this->_assyPartsInfo[$part]["prods"][$date];
            
            if ($this->_assyPartsInfo[$part]["dmds"][$date]) {
                // 该日有需求时，如果累计需求量比前一日的外库 数量多，才进行库存转移。
                // 尽量保证外库数量与覆盖累计需求量相同（但不能超过整体库存量）,并需要进行内外库库存转移
                if ($this->_assyPartsInfo[$part]["consectAccuDmds"][$date] < $this->_assyPartsInfo[$part]["stocks"][$date]) {
                    if ($pOuterstock - $pDmd >= $this->_assyPartsInfo[$part]["consectAccuDmds"][$date]) {
                        $this->_assyPartsInfo[$part]["outerStocks"][$date] = $pOuterstock - $pDmd;
                    } else {
                        $this->_assyPartsInfo[$part]["outerStocks"][$date] = $this->_assyPartsInfo[$part]["consectAccuDmds"][$date];
                    }
                } else {
                    $this->_assyPartsInfo[$part]["outerStocks"][$date] = $this->_assyPartsInfo[$part]["stocks"][$date];
                }
            } else {
                // 否则，外库数量需要扣减前日需求量   不需要进行内外库库存转移,
                $this->_assyPartsInfo[$part]["outerStocks"][$date] = $pOuterstock - $pDmd;
            }
            $this->_assyPartsInfo[$part]["innerStocks"][$date] = $this->_assyPartsInfo[$part]["stocks"][$date] - $this->_assyPartsInfo[$part]["outerStocks"][$date];
            if ($this->_assyPartsInfo[$part]["innerStocks"][$date] < $this->_assyPartsInfo[$part]["prods"][$date]) {
                // 确保当日生产量部分必须落入内库库存
                $this->_assyPartsInfo[$part]["innerStocks"][$date] = $this->_assyPartsInfo[$part]["prods"][$date];
                $this->_assyPartsInfo[$part]["outerStocks"][$date] = $this->_assyPartsInfo[$part]["stocks"][$date] - $this->_assyPartsInfo[$part]["innerStocks"][$date];
            }
            
            
            
            $prevdate = $date;
        }
        
    }

    
    protected function arrangeMinAssyProduction($part, $prodDate, $curNetDmd)
    {
        if ($this->_availAssyDayCapacities[$prodDate] >= $curNetDmd) {
            // 如果'生产日'剩余产能大于等于该零件的当前净需求，可以安排满足全额净需求的生产
            $this->_assyPartsInfo[$part]["prods"][$prodDate] += floor($curNetDmd / 2) * 2;
        
            // 对对应“生产日”进行剩余产能扣减
            $this->_availAssyDayCapacities[$prodDate] -= floor($curNetDmd / 2) * 2;
        } else {
            $leftCapacity = $this->_availAssyDayCapacities[$prodDate];
            
            if ($prodDate == $this->_today) {
                // 如果生产日是第一活动日，没办法向前递推，只能安排在该天超额生产
                $this->_assyPartsInfo[$part]["prods"][$prodDate] += ceil($curNetDmd / 2) * 2;
                $this->_availAssyDayCapacities[$prodDate] -= ceil($leftCapacity / 2) * 2;
                return;
            }
            
            // 如果生产日非第一活动日，继续：
            
            // 如果'生产日'剩余产能已经无法满足该零件的净需求，只能安排将剩余产能全部投入生产(前提是剩余产能仍然大于0)
            if ($leftCapacity > 0) {
                $this->_assyPartsInfo[$part]["prods"][$prodDate] += ceil($leftCapacity / 2) * 2;
            }
        
            // 此时对应“生产日”剩余产能将耗尽
            $this->_availAssyDayCapacities[$prodDate] -= ceil($leftCapacity / 2) * 2;
        
            // 同时，还需将不足的需求部分，移到前一个"可生产日"进行生产(该步骤可递归进行)
            $curNetDmd -= ceil($leftCapacity / 2) * 2;
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
    
    /**
     * @param array $arr : array with keys in ascending order
     * @param unknown $startKey
     * @param unknown $len
     * @return unknown[]
     */
    public static function getSubArrayFromKey($arr, $startKey, $len = 999)
    {
        $subArr = [];
        foreach ($arr as $key => $val) {
            if ($len == 0)  {
                break;
            }
            if ($key >= $startKey) {
                $subArr[$key] = $val;
                $len--;
            }
        }
        
        return $subArr;
    }
    
    /**
     * @param unknown $arr : array with keys in ascending order
     * @param unknown $toKey
     * @return unknown[]
     */
    public static function getSubArrayToKey ($arr, $toKey)
    {
        $subArr = [];
        foreach ($arr as $key => $val) {
            if ($key <= $toKey) {
                $subArr[$key] = $val;
            }
        }
        
        return $subArr;
    }
    
    
    
    
    public function getClassClosestWorkdayDate($class, $date, $allowCurrent = true)
    {
        $wdMap = $this->getClassIsWeekdayWorkableMap($class);
        $curWd = date("N", strtotime($date));
        
        if ($wdMap[$curWd] && $allowCurrent) {
            return $date;
        }
        
        if ($date == $this->_today) {
            return $date;
        }
        
        $D = new \DateTime($date);
        do {
            $D->sub(new \DateInterval('P1D'));
            $d = $D->format("Y-m-d");
            // 如果是第一活动天，强制作为最近工作日
            if ($d == $this->_today) {
                break;
            }
        } while (!self::isWorkdayByClassAndDate($class, $d));
        
        return $d;
    }
    
    
    public function test2()
    {
        dump(self::getSubArrayBeforeKey($this->getAssyActiveDates(), '2017-04-23'));
    }
    
    
    public function index ()
    {
        $this->DoAssemblyMrp();
        

        $this->assign("dates", $this->getAssyActiveDates());
        $this->assign("isPeriodDateMap", $this->getAssyIsPeriodDateMap());
        $this->assign("isDoubleShiftDateMap", $this->getAssyIsDoubleShiftDateMap());
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
    
    static function getDateBefore($date)
    {
        return date('Y-m-d', strtotime($date) - 86400);
    }
    
    static function getDatesBetween($startdate, $enddate)
    {
    
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
    
    static function getMondaysBetween ($startdate, $enddate)
    {
        $days = self::getDatesBetween($startdate, $enddate);
        $mondays = [];
        foreach ($days as $day) {
            if (date("N", strtotime($day)) == 1) {
                $mondays[] = $day;
            }
        }

        return $mondays;
    }
    
    
    static function getFirstMonthDayBetween ($startdate, $enddate)
    {
        $days = self::getDatesBetween($startdate, $enddate);
        $fmdays = [];
        foreach ($days as $day) {
            if (date("d", strtotime($day)) == 1) {
                $fmdays[] = $day;
            }
        }
        
        return $fmdays;
    }
}