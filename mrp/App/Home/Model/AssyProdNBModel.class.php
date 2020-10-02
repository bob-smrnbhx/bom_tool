<?php

namespace Home\Model;
import("Date.DateHelper");


class AssyProdNBModel
{
    private $_dmdModel;
    protected $_proj;
    

    //protected $_maxConfig = 4; // or 2
    private $saftyDayLength = 4;

    protected $conds = [];
    
    protected $_site;
    
    protected $isWeekdayWorkableMap = [
    ];
    protected $isWorkdayDateMap = [];
    
    /**
     * 是否被优先共线项目生产占用的日期的映射
     * @var array
     */
    protected $isOccupiedDateMap = [];

    
    /**
     * 每日的总需求量数组，从初始日期开始（第一个活动日前一天）
     * @var array
     */
    protected $totalDayDmds = [];
    /**
     * 每日的总生产量数组，从初始日期开始，从第一个活动日开始
     * @var array
     */
    protected $totalDayProds = [];
    
    protected $dayCapacities = [];
    /**
     * 每日的剩余可用产能跟踪数组，从第一个活动日开始
     * @var array
     */
    protected $availDayCapacities = [];
    
    
    /**
     * 所有项目的通用SQL条件
     */
    protected $baseConds = [
            'ptp_pm_code' => 'L',
            'ptp_status'  => 'AC'
    ];
    
    protected $projAssyOptions = [
            /*
             * 项目的特定SQL条件
             */
            "specConds" => [
                'cd539' => [
                        'lnd_line'    => 'A00016',
                        ]
            ],
            
            /*
             * 项目的每班总大产能
             * */
            "shiftCapacity" => [
                'cd539'  => 720,
            ],
            
            /*
             * 项目每周几的班次，可以1班、2班等，如果为0，表示该周几为休息日
             * use a default rules for all projects
             * */
            "weekdayShiftRules" => [
                    "" => [
                        1 => 1,
                        2 => 1,
                        3 => 1,
                        4 => 1,
                        5 => 1,
                        6 => 1,
                        7 => 0
                    ]
            ],
            
            /*
             * 项目的所有配置
             * */
            "classes" => [
                    'cd539'  => ['A', 'B', 'C', 'D', 'E'],
            ],
            

            
            // the binary numbers indicating weekday work state, from Sunday to Saturday
            'weekdayWorkRules' => [
                    'cd539'  => 0b0111111,
            ],
            

            
            /*
             * 项目是否使用连续累计需求日安全库存覆盖规则
             * */
            "useSaftyStockRule" => [
                    'cd539'  => true,
            ],
            
            
            /*
             * 项目的生产提前期。
             * 一般而言，应用了连续累计需求日安全库存规则的项目，不需要再考虑提前期（0为不考虑）。
             * */
            "leadDayLen" => [
                    'cd539'  => 0,
            ],
            
            /*
             * 项目的每日产量是否需要尽量排满产
             * */
            "useComplement" => [
                'cd539'  => true,
            ],
            
            /* 
             * 项目总生产数是否参考上一个工作日的总需求量
             * 一般，只有在项目的每日产量不需要排满产时，该值为true才有意义
             * */
            "prodRefLastWorkdayTotalDmd" => [
                "cd539"  => false,
            ],
            
            
            "preferentialCommonProjs" => [
                "cd539"  => [],
            ],
            
            /*
             * 是否允许总生产数，以刚超过该日产能的最小托数数量，超出产能
             * */
            "allowOverCapacityInMinPallets" => [
                'cd539'  => false,
            ],
            
            /*
             * 是否使用当日外库库存，而不是当日总库存，来进行累计需求量的判断
             * */
            "useOuterStockForSafty" => [
                    'cd539'  => false,
            ],
            
            

    ];
    
    /**
     * @var array
     */
    protected $weekdayShiftsRules;
    protected $weekdayWorkRules;
    
 
    
    protected $specConds = [];
    protected $shiftCapacity;
    protected $prodToOuterStockMovementDelayedWorkDay;
    protected $useSaftyStockRule = true;
    protected $leadDayLen = 0;
    protected $useComplement = true;
    protected $useOuterStockForSafty = false;
    protected $prodRefLastExistingTotalDmd = false;
    protected $allowOverCapacityInMinPallets = false;
    protected $preferentialCommonProjs = [];
    
    protected $useBoxLimitation = false;
    protected $totalBoxAmount;
    protected $qtyPerBox;
    
    
    
    protected $baseProjConds = [];
    

    
    protected $partsInfo = [];
    
    protected $activeDates = [];
    protected $activeDayDates = [];
    protected $activeWeekDates = [];
    protected $activeMonthDates = [];
    protected $initFirstWDate;
    protected $dateTypeMap = [];
    protected $belongedPeriodDateMap = [];
    
//     protected $daysLength = 11;
//     protected $weeksLength = 6;
//     protected $monthsLength;
    
    protected $today = '';
    protected $orgDate = '';
    
    protected $_depth = [];
    
    
    protected function getDmdModel ()
    {
        if (empty($this->_dmdModel)) {
            $this->_dmdModel = M('assy_dmd');
        }
        
        return $this->_dmdModel;
    }
    

    
    public function addConds(array $newConds)
    {
        $this->conds = array_replace($this->getConds(), $newConds);
    }
    
    protected function getConds()
    {
        if (empty($this->conds)) {
            $this->conds = $this->baseConds;
        }
        
        return $this->conds;
    }
    
    
    public function setStartDate($date)
    {
        $this->addConds([
                "dmd_date" => ['EGT', $this->orgDate]
        ]);
    }
    
    protected function getAllClasses ()
    {
        return $this->projAssyOptions["classes"][$this->_proj];
    }
    
    public function __construct ($site, $proj)
    {


        
        $this->_proj = $proj;
        if ($site) {
            $site = strval($site);
            $this->_site = $site;
            $this->addConds([
               "ptp_site" => $site     
            ]);
        }


        if (!isset($this->projAssyOptions["specConds"][$this->_proj])) {
            throw new \Exception("invalid project name provided");
        }
        
        // set properties according to specified project
        $this->specConds = $this->projAssyOptions["specConds"][$this->_proj];
        $this->addConds($this->specConds);
        
        $this->shiftCapacity = $this->projAssyOptions["shiftCapacity"][$this->_proj];
        $this->useSaftyStockRule = $this->projAssyOptions["useSaftyStockRule"][$this->_proj];
        $this->leadDayLen = $this->projAssyOptions["leadDayLen"][$this->_proj];
        $this->prodToOuterStockMovementDelayedWorkDay = $this->projAssyOptions["prodToOuterStockMovementDelayedWorkDay"][$this->_proj];
        $this->useComplement =  $this->projAssyOptions["useComplement"][$this->_proj];
        $this->prodRefLastExistingTotalDmd = $this->projAssyOptions["prodRefLastWorkdayTotalDmd"][$this->_proj];
        $this->allowOverCapacityInMinPallets = $this->projAssyOptions["allowOverCapacityInMinPallets"][$this->_proj];
 
        $this->useOuterStockForSafty = $this->projAssyOptions["useOuterStockForSafty"][$this->_proj];
        $this->preferentialCommonProjs = $this->projAssyOptions["preferentialCommonProjs"][$this->_proj];
        
        $this->weekdayWorkRules = $this->projAssyOptions["weekdayWorkRules"][$this->_proj];
        $this->weekdayShiftsRules = isset($this->projAssyOptions["weekdayShiftRules"][$this->_proj]) ? $this->projAssyOptions["weekdayShiftRules"][$this->_proj] : $this->projAssyOptions["weekdayShiftRules"][''];
        
        
        $this->classes = $this->projAssyOptions["classes"][$this->_proj];
        
 
        
        $cwMap = self::convertBinToWeekdayMap($this->weekdayWorkRules);
        $cwMap[7] = $cwMap[0];
        unset($cwMap[0]);
        foreach ($cwMap as &$is) {
            $is = (bool)$is;
        }
        $this->isWeekdayWorkableMap = $cwMap;
        
        
        // 获取所有活动日期
        $this->getActiveDates();

        // 初始化每日最大产能，从第一个活动日开始
        $this->availDayCapacities = $this->getCapacities();

        // 获取所有装配零件信息， 该操作逻辑上必须放在计算并保存活动日期之后
        $partsInfo = $this->getPartsInfo();

        
        // 初始化每日的总需求量,从初始日期开始，因为总需求量在某些项目中会被之后的总生产量考虑到
        $orgDate = $this->getOrgDate();
        foreach ($partsInfo as $part => $partInfo) {
            $this->totalDayDmds[$orgDate] += $partInfo["dmds"][$orgDate];
//             foreach ($this->activeDayDates as $date) {
//                 $this->totalDayDmds[$date] += $partInfo["dmds"][$date];
//             }
            foreach ($this->activeDates as $date) {
                $this->totalDayDmds[$date] += $partInfo["dmds"][$date];
            }
        }
        
        
    }
    
    public function isSaftyStockRuleUsed ()
    {
        return $this->useSaftyStockRule;
    }
    
    
    protected static function convertBinToWeekdayMap ($bin)
    {
        return str_split(str_pad(decbin($bin), 7, 0, STR_PAD_LEFT));
    }
    


    
    
    /**
     * 获取活动需求日的第一天
     * @return string
     */
    public function getStartActiveDate ()
    {
        if (empty($this->today)) {
            switch($this->_site) {
                case 1000:
                    $map["ptp_site"] = '1000';
                    $map["ptp_pm_code"] = 'L';
                    break;
                case 6000:
                    $map["ptp_site"] = '6000';
                    $map["ptp_pm_code"] = 'L';
                    break;
            }
            
            $this->today = M("ptp_stock_detail")->where($map)->max("in_date");
        }
        
        return $this->today;
    }
    
 
    
    
    /**
     * 获取所有活动需求日的前一天，作为初始前一天
     * @return string
     */
    public function getOrgDate ()
    {
        if (empty($this->orgDate)) {
            $this->orgDate = \DateHelper::getDateBefore($this->getStartActiveDate());         
        }
        
        return $this->orgDate;
    }
    
 
    
    /**
     * 根据固定的规则，统一计算并获取所有的可用活动日期。
     * 按照预定义的规则来获取
     */
    public function getActiveDates ()
    {
        if (empty($this->activeDates)) {

            $orgDate = $this->getOrgDate();
            $this->dateTypeMap[$orgDate] =  'd';
 
            
            // 认定从今天开始，直到第2个周一前，都是862需求日
            // 算法：从今天开始逐日递推，在遇到第三个周一前（今天如果为周一也包括在计数内）递推结束
            $startDdate = $this->getStartActiveDate();
            $mondayCount = 1;
            $date = $startDdate;
            


            
            if (\DateHelper::isMonday($date)) {
                $mondayCount--;
            }
            while ($mondayCount >= 0) {
                $this->activeDayDates[] = $date;
                
                $date = \DateHelper::getDateAfter($date);
                if (\DateHelper::isMonday($date)) {
                    $mondayCount--;
                } 
            }
            
            foreach ($this->activeDayDates as $date) {
                $this->dateTypeMap[$date] =  'd';
            }
            
            // 认定从最后一个862需求日开始，之后的连续4个周一的日期，为830周需求日
            $endDdate = end($this->activeDayDates);
            $mondayCount = 3;
            $date = $endDdate;
            while ($mondayCount > 0) {
                $date = \DateHelper::getDateAfter($date);
                if (\DateHelper::isMonday($date)) {
                    $this->activeWeekDates[] = $date;
                    $mondayCount--;
                }
            }
 
            /// 将第一个830周一取出进行分解，每日子日期视为862需求日
            //$this->initFirstWDate = array_shift($this->activeWeekDates);
            //$this->decomposeInitFirstWDate();
            
            
            foreach ($this->activeWeekDates as $date) {
                $this->dateTypeMap[$date] =  'w';
            }
            

            
            
            
            // 认定从最后一个862需求日开始，之后的连续三个月1的日期，为830周需求日
            $endWdate = end($this->activeWeekDates);
            $monthFirstdayCount = 3;
            $date = $endWdate;
            while ($monthFirstdayCount > 0) {
                $date = \DateHelper::getDateAfter($date);
                if (\DateHelper::isMonthFirstDay($date)) {
                    $this->activeMonthDates[] = $date;
                    $monthFirstdayCount--;
                }
            }
            
            foreach ($this->activeMonthDates as $date) {
                $this->dateTypeMap[$date] =  'm';
            }
    
    
            $this->activeDates = array_merge($this->activeDayDates, $this->activeWeekDates, $this->activeMonthDates);
    
        }
        return $this->activeDates;
    }
    
 
    
    protected function decomposeInitFirstWDate ()
    {
        for ($i = 0; $i < 7; $i++) {
            $date = \DateHelper::getDateAfter($this->initFirstWDate, $i);
            $this->activeDayDates[] = $date;
            $this->dateTypeMap[$date] =  'd';
        }
    }
    

    
    public function getDateTypeMap ()
    {
        return $this->dateTypeMap;
    }
    
    public function getFormattedDates ()
    {
        $wdMap = [];
        foreach ($this->activeDates as $date) {
            $wdMap[$date] = \DateHelper::getFormattedWeekday($date, $this->dateTypeMap[$date]);
        }
        
        return $wdMap;
    }
    
    /**
     * 获取所有日期是否是830日期的判断映射
     * @return boolean[]
     */
    public function getAssyIsPeriodDateMap ()
    {
        $dates = $this->getActiveDates();
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
    
    
    /**
     * 判断是否是862日期
     * @param unknown $date
     * @return boolean
     */
    protected function isDayDate ($date)
    {
        return $this->dateTypeMap[$date] == 'd';
    }
    
    protected function isWeekDate ($date)
    {
        return $this->dateTypeMap[$date] == 'w';
    }
    
    protected function isMonthDate ($date)
    {
        return $this->dateTypeMap[$date] == 'm';
    }
    
    protected function getBelongedPeriodDate ($date)
    {
        if (!isset($this->belongedPeriodDateMap[$date])) {
            if ($this->isDayDate($date) || $this->isWeekDate($date)) {
                $this->belongedPeriodDateMap[$date] = $date;
            } else {
                if ($date < $this->activeMonthDates[0]) {
                    // abandon all weird week dates
                    $this->belongedPeriodDateMap['0000-00-00'] = $date;
                } else {
                    $pddates = array_merge($this->activeWeekDates, $this->activeMonthDates);
                    sort($pddates);
                    
                    
                     
                    $psdate = $pedate = '';
                    foreach ($pddates as $pddate) {
                        if ($date >= $pddate) {
                            $psdate = $pddate;
                        } else {
                            break;
                        }
                    }
                    
                    $this->belongedPeriodDateMap[$date] = $psdate;
                }
            }
            
            
 
        }
        
        return $this->belongedPeriodDateMap[$date];
        

    }
    
 
    
    /**
     * 判断日期是否是可工作日，不分配置类型进行通用判断
     * 并且考虑共线，优先级低的项目会避开优先级高的共线项目的生产日。
     * @param string $date
     * @return boolean
     */
    protected function isWorkday ($date)
    {
        if (!$this->isDayDate($date)) {
            // 非862类型日期始终允许为工作日
            return true;
        }
        
        $wd = date("N", strtotime($date));
        return !$this->isOccupiedDateMap[$date] && $this->isWeekdayWorkableMap[$wd];
    }
    
    protected function getNextWorkDate ($curDate)
    {
        $nextDate = $curDate;
        do {
            $nextDate = \DateHelper::getDateAfter($nextDate);
            if (!$this->isDayDate($nextDate)) {
                return false;
            }
        } while (!$this->isWorkday($nextDate));
        
        return $nextDate;
    }
    
    protected function getPrevWorkDate ($curDate)
    {
        $prevDate = $curDate;
        do {
            $prevDate = \DateHelper::getDateBefore($prevDate);
            if (!$this->isDayDate($prevDate)) {
                return true;
            }
        } while (!$this->isWorkday($prevDate) || $prevDate = $this->getStartActiveDate());
    
        return $prevDate;
    }
    
    /**
     * 判断该项目指定日期是否是带有任何物料的客户需求，部分项目要求只有在这种日期时才能生产
     * @param unknown $date
     */
    protected function isDmdDay ($date)
    {
        return $this->totalDayDmds[$date] ? true : false;
    }
    
   
    /**
     * 判断该项目的指定日期是否在应用之前的规则后，是否至少需要进行一定量的生产，部分项目要求只有在这种日期时才能生产。
     * @param unknown $date
     * @return boolean
     */
    protected function isMinProdRequiredDay ($date)
    {
        return $this->totalDayProds[$date] ? true : false;
    }
    
    
    

    
    /**
     * 获取提前期之后的客户需求日
     * 非工作日不包括在提前期之内。
     * @param unknown $date
     * @return string
     */
    public function getDmdDateAfterLeadday ($date)
    {
        if (!$this->isDayDate($date)) {
            return $date;
        }
        
        $curLead = $this->leadDayLen;
        
        while ($curLead > 0) {
            $date = \DateHelper::getDateAfter($date, 1);
            
            
            if (!$this->isDayDate($date)) {
                // 如果碰到830，直接返回830日期
                return $date;
            }
            
            
            if ($this->isWorkday($date) || $this->isDmdDay($date)) {
                $curLead--;
            }
        }
        

    
        return $date;
    }
    
 
    
    /**
     * 只考虑860日期产能，830日期产能不考虑
     * @return number[]
     */
    protected function getCapacityDateMap ()
    {
        $dates = $this->getActiveDates();
        $map = [];
        foreach ($dates as $date) {
            if ($this->isDayDate($date)) {
                $wd = date("N", strtotime($date));
                $map[$date] = $this->weekdayShiftsRules[$wd] * $this->shiftCapacity;
            }
        }

        return $map;
    }
    

    
    public function getIsDoubleShiftDateMap ()
    {
        $dates = $this->getActiveDates();
        $map = [];
        foreach ($dates as $date) {
            $wd = date("N", strtotime($date));
        
            if ($this->weekdayShiftsRules[$wd] == 2) {
                $map[$date] = true;
            } else {
                $map[$date] = false;
            }
        
        }
 
        
        return $map;
    }
    
    
    
    public function getCapacityByDate ($date)
    {
        $wd = date("N", strtotime($date));
        return $this->weekdayShiftsRules[$wd] * $this->shiftCapacity;
    }
    
    public function getCapacities ()
    {
        if (empty($this->dayCapacities)) {
            foreach ($this->activeDayDates as $date) {
                $wd = date("N", strtotime($date));
                $this->dayCapacities[$date] = $this->weekdayShiftsRules[$wd] * $this->shiftCapacity;
            }
        }
        
        return $this->dayCapacities;
    }
    
    public function getAvailCapacities ()
    {
        return $this->availDayCapacities;
    }
    
    public function getTotalDayDmds ()
    {
        return $this->totalDayDmds;
    }
    
    public function getTotalDayProds ()
    {
        $this->recalculateTotalDayProds();
        return $this->totalDayProds;
    }

    public function getIsWorkdayDateMap ()
    {
        $map = [];
        foreach ($this->getActiveDates() as $date) {
            $map[$date] = $this->isWorkday($date);
        }
    
        return $map;
    }
    

    /**
     * 获取零件提前期之后的客户需求数
     * @param unknown $part
     * @param unknown $date
     * @return number
     */
    protected function getDmdAfterLeadedDay($part, $date)
    {
        $dmdDate = $this->getDmdDateAfterLeadday($date);
        if ($this->isDayDate($dmdDate)) {
            return $this->partsInfo[$part]["dmds"][$dmdDate];
        }
    
        // 非日类型需求，返回0.
        return 0;
    }
 
    
    public function getPartsInfo()
    {
        if (empty($this->partsInfo)) {
            
            //         $conds["dmd_date"] = [
            //                 ['EGT', \DateHelper::getDateBefore($this->getStartActiveDate())],
            //                 ['EXP', 'is null'],
            //                 'OR'
            //         ];
            //只获取在最近的库存日期之后的客户需求

            $this->setStartDate($this->getOrgDate());
            $conds = $this->getConds();
            
            $activeDates = $this->getActiveDates();
            $startActiveDate = $this->getStartActiveDate();
            $orgDate = $this->getOrgDate();            
            

            $result = $this->getDmdModel()->where($conds)->order("ptp_part")->select();
            
            

            foreach ($result as $row) { 
                $part = $row["ptp_part"];
                

                
                if (!isset($this->partsInfo[$part])) {
                    $class = '';
                    $this->partsInfo[$part] = [
                            'isMrp'    => (bool)$row["ptp_isdmrp"],
                            'part'     => $row["ptp_part"],
                            'site'     => $row["ptp_site"],
                            'desc1'    => $row["ptp_desc1"],
                            'desc2'    => $row["ptp_desc2"],
                            "isAnte"   => $row["is_ante"],
                            'buyer'    => $row["ptp_buyer"],
                            'line'     => $row["lnd_line"],
                            'mfgLead'  => $row["ptp_mfg_lead"],
                            'ordMin'   => floatval($row["ptp_ord_min"]),
                            'class'    => $class,
                            'dmds'     => [],    // 只保存有需求量的需求日
                            'prods'    => [],    // 每天都保存
                            'complements' => [],
                            'innerStocks' => [], // 每天都保存
                            'outerStocks' => [], // 每天都保存
                            'stocks'   => [],     // 每天都保存
                            'consectAccuDmds' => [],
                            'useOuterStockForSafty' => $this->useOuterStockForSafty
                    ];
                }
                
                if ($this->_proj == 'cd539') {
                    $this->partsInfo[$part]["immediateProdStock"] = 1;
                }
                
                
                // 如果制造件不需要重新进行mrp，直接读取对应的生产计划数据即可
                if (!$this->partsInfo[$part]["isMrp"]) {
                    // 已有某日期的计划数据时才进行记录
                    if ($row['drps_date'] != null && !empty($row['drps_qty']) && !isset($this->partsInfo[$part]['prods'][$row['drps_date']])) {
                        $this->partsInfo[$part]['prods'][$row['drps_date']] =  floatval($row['drps_qty']);
                    }
                }
                
                

                
                if (!isset($this->partsInfo[$part]["f830Date"]) && $row['dmd_f830_date']) {
                    $this->partsInfo[$part]["f830Date"] = $row['dmd_f830_date'];
                }
                
                if (!isset($this->partsInfo[$part]["f830Qty"]) && $row['dmd_f830_qty']) {
                    $this->partsInfo[$part]["f830Qty"] = $row['dmd_f830_qty'];
                }
                
                // 将当日未进行任何生产前导出的库存数据（in_date字段仍然为当天)，视为昨日的最终库存
                
                if (!isset($this->partsInfo[$part]["innerStocks"][$orgDate]) && $row['in_date'] == $startActiveDate && $row["in_type"] == 'i') {
                    $this->partsInfo[$part]["innerStocks"][$orgDate] = floatval($row["in_qty_oh"]);
                    

                }
                
                if (!isset($this->partsInfo[$part]["outerStocks"][$orgDate]) && $row['in_date'] == $startActiveDate && $row["in_type"] == 'o') {
                    $this->partsInfo[$part]["outerStocks"][$orgDate] = floatval($row["in_qty_oh"]);
                }
                
                
                // 对于830日期，准备进行多个可能的阶段日期累加。
                $bdate = $this->getBelongedPeriodDate($row["dmd_date"]);

                
                
                // 同个物料一个需求数，可能属于多个订单。每个830日期，可能包含多个需求日
                if (!isset($this->partsInfo[$part]['dmds'][$bdate][$row["sod_ship"]][$row["dmd_date"]])) {
                    $this->partsInfo[$part]['dmds'][$bdate][$row["sod_ship"]][$row["dmd_date"]] = floatval($row['dmd_qty']);
                }
            }

            

            foreach ($this->partsInfo as $part => $partInfo) {
                // 叠加所有订单的862需求量和初始需求量
                
                foreach ($this->partsInfo[$part]['dmds'] as $bdate => $shipDmds) {
                    foreach ($shipDmds as $ship => $dmds) {
                        $shipDmds[$ship] = array_sum($dmds);
                    }
                    $this->partsInfo[$part]['dmds'][$bdate] = array_sum($shipDmds);
                }
 

                // 计算初始日期的实际外库库存和实际总库存量
                $this->partsInfo[$part]["outerStocks"][$orgDate] = $this->partsInfo[$part]["outerStocks"][$orgDate] - $this->partsInfo[$part]["dmds"][$orgDate];
                $this->partsInfo[$part]['stocks'][$orgDate] = $this->partsInfo[$part]["innerStocks"][$orgDate] + $this->partsInfo[$part]["outerStocks"][$orgDate];
                

                
                
                // 进行第一个830周分解
                if ($this->partsInfo[$part]["isAnte"]) {
                    // ante就不分解了，全部放到周一，否则原规则会导致整周每次都生产
                } else {
                    if (isset($this->partsInfo[$part]["f830Date"])) {
                        $fsdate = $this->partsInfo[$part]["f830Date"];
                        $fwqty = $this->partsInfo[$part]["f830Qty"];
                        
                        $fedate = \DateHelper::getDateAfter($fsdate, 5);
                        $fwdates = \DateHelper::getDatesBetween($fsdate, $fedate);
                        $aedate = '';
                        foreach ($fwdates as $date) {
                            if ($this->partsInfo[$part]["dmds"][$date]) {
                                $aedate = $date;
                            }
                        }
                        $decompDates = [];
                        foreach ($fwdates as $key => $date) {
                            if ($date > $aedate) {
                                $decompDates[] = $date;
                            }
                        }
                        $orgcompDates =  \DateHelper::getDatesBetween($fsdate, $aedate);
                        foreach ($orgcompDates as $orgcompdate) {
                            $fwqty -= $this->partsInfo[$part]["dmds"][$orgcompdate];
                        }
                         

                        if ($fwqty > 0) {
                            $deCounts = count($decompDates);
                            foreach ($decompDates as $date) {
                                if (floor($fwqty / $deCounts)) {
                                    $this->partsInfo[$part]["dmds"][$date] = floor($fwqty / $deCounts);
                                }
                            }
                            if ($fwqty) {
                                $this->partsInfo[$part]["dmds"][$fedate] += $fwqty % $deCounts;
                            }
                            
                        } 
 

                    }

                }


                
                // 每个零件的日需求量映射，按日期从小到大排序。
                ksort($this->partsInfo[$part]['dmds']);
            
                // 累计每个零件的所有活动日总需求量作为参考
                //$this->partsInfo[$part]["dmdsSum"] =  array_sum($this->partsInfo[$part]['dmds']) - $this->partsInfo[$part]['dmds'][$orgDate];
                
//                 // 累计每个零件的活动日的862需求总需求量作为参考
                $this->partsInfo[$part]["dmdsSum"] = 0;
                foreach ($this->partsInfo[$part]['dmds'] as $date => $qty) {
                    if ($this->isDayDate($date) && $date != $orgDate) {
                        $this->partsInfo[$part]["dmdsSum"] += $qty;
                    }
                }
            
                // 活动日862总需求量为0的零件，不参与生产计划安排
                $this->partsInfo[$part]["hasDmds"] = !empty($this->partsInfo[$part]["dmdsSum"]);
                
                foreach ($activeDates as $date) {
                    // 获取每个零件每日的从此以后的累计覆盖日需求量
                    $this->getPartAccuDayDmdFrom($part, $date);
                }
                
                
                //var_dump($this->partsInfo[$part]);
            }
            
 
        
        }
        
        return $this->partsInfo;
    }
    
    
 
    
    
    
    protected function getPartsInfoByClass ($classes)
    {
        if (!is_array($classes)) {
            $classes = [$classes];
        }
        $this->getPartsInfo();
        $filteredPartsInfo = [];
        // 务必保持内部数据和返回数据的数组项间的引用关联
        foreach ($classes as $class) {
            foreach ($this->partsInfo as $part => $partInfo) {
                if ($this->partsInfo[$part]['class'] == $class) {
                    $filteredPartsInfo[$part] = $this->partsInfo[$part]['class'];
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
        
        
        
        $activeDates = $this->getActiveDates();
        $orgDate = $this->getOrgDate();
        $classNetAccuDmds = [];
        foreach ($this->partsInfo as $part => $partInfo) {
            $class = $partInfo["class"];
            $i = 0;
            
            $curDateIndex = array_search($fromDate, $activeDates);
            // 只考虑日类型需求作为凭据
            if ($curDateIndex + $accuLength > $this->daysLength) {
                $accuLength =  $this->daysLength - $curDateIndex;
            }

            
            $prevDate = \DateHelper::getDateBefore($fromDate);
            $prevStock = $partInfo["stocks"][$prevDate];
            
            $consectPartDmd = $this->getPartAccuDayDmdFrom($part, $prevDate, $accuLength);
            
 
            
            $classNetAccuDmds[$class] += $consectPartDmd -  $prevStock;

        }
        
        return $classNetAccuDmds;
    }
    
    protected function getAssyClassesByNetAccuDmdsOrder ($fromDate = '', $accuLength = 0)
    {
        $classAccuDmds = $this->getAssyClassNetAccuDmds($fromDate, $accuLength);
        arsort($classAccuDmds);
        return array_keys($classAccuDmds);
    }
    

    
    /**
     * 判断零件是否在指定日期之后（不包含该日期），有目前（在该日期进行排产时）需要考虑的需求量
     * 非最后一个862日期，只考虑之后的862日期是否有需求；
     * 最后一个862日期，只考虑第一个830日期是否有需求
     * 所有830日期，只要任何一个之后的830日期有需求即可
     * @param unknown $part
     * @param unknown $date
     * @return boolean
     */
    protected function hasDayDmdsFrom($part, $date, $len = null)
    {
        $lastActiveDayDate = end($this->activeDayDates);
        
        
        if ($date >= $lastActiveDayDate) {
            return false;
        }
        if (empty($len)) {
            $lastDate = $lastActiveDayDate;
        } else {
            $lastDate = \DateHelper::getDateAfter($date, $len);
            if ($lastDate > $lastActiveDayDate) {
                $lastDate = $lastActiveDayDate;
            }
        }

        
        
        if ($this->isDayDate($date)) {
            if ($date < $lastDate) {
                foreach ($this->activeDayDates as $curDate) {
                    if ($curDate >= $date && $curDate <= $lastDate && $this->partsInfo[$part]['dmds'][$curDate] != 0) {
                        return true;
                    }
                }
            } 
            
//             else if ($date == $lastActiveDayDate) {
//                 $startActiveWeekDate = $this->activeWeekDates[0];
//                 if ($this->partsInfo[$part]['dmds'][$startActiveWeekDate]) {
//                     return true;
//                 }
//             }
        } else {
            foreach (array_merge($this->activeWeekDates, $this->activeMonthDates) as $curDate) {
                if ($curDate >= $date && $this->partsInfo[$part]['dmds'][$curDate] != 0) {
                    return true;
                }
            }
        }
    
        return false;
    }
    
    
    public function getMrpedParts()
    {
        $parts = [];
        foreach ($this->partsInfo as $part => $partInfo) {
            if (!$partInfo["isMrp"]) {
                $parts[] = $part;
            }
        }
        
        return $parts;
    }
    
    /**
     * 获取所有未进行mrp的零件
     * @return string[]
     */
    protected function getUnmrpedParts ()
    {
        $parts = [];
        foreach ($this->partsInfo as $part => $partInfo) {
            if ($partInfo["isMrp"]) {
                $parts[] = $part;
            }
        }
        
        return $parts;
    }
    
    protected function getDmdedUnmrpedParts ()
    {
        $parts = [];
        foreach ($this->partsInfo as $part => $partInfo) {
            if ($partInfo["hasDmds"] && $partInfo["isMrp"]) {
                $parts[] = $part;
            }
        }
        
        return $parts;
    }
    
    protected function getUndmdedUnmrpedParts ()
    {
        $parts = [];
        foreach ($this->partsInfo as $part => $partInfo) {
            if (!$partInfo["hasDmds"] && $partInfo["isMrp"]) {
                $parts[] = $part;
            }
        }
    
        return $parts;
    }
    
    protected function getOrderedPartsByOrdMin ($parts)
    {
        usort($parts, function($part1, $part2) {
            if ($this->partsInfo[$part1]["ordMin"] != $this->partsInfo[$part2]["ordMin"]) {
                return $this->partsInfo[$part2]["ordMin"] - $this->partsInfo[$part1]["ordMin"];
            }
            
            return $this->partsInfo[$part2]["part"] - $this->partsInfo[$part1]["part"];
        });
        
        return $parts;
    }
    
    
    /**
     * 将所有未进行mrp的零件，按照预计的产量进行从大到小的优先级排序。
     * @param string $fromDate
     * @param number $accuLength
     * @return array
     */
    protected function getUnmrpedPartsByPredictedProdPriority ($curDate = '', $accuLength = 0)
    {
        //先根据区间的累计净需求按照从大到小排序
        $accuLength = intval($accuLength);
        if ($accuLength < 1) {
            $accuLength = 9999;
        }
        
 
        $partAccuDmdMap = [];
        $activeDates = self::getActiveDates();
        $orgDate = $this->getOrgDate();
        foreach ($this->partsInfo as $part => $partInfo) {
            if (!$partInfo["isMrp"]) {
                continue;
            }

            $prevDate = \DateHelper::getDateBefore($curDate);
            $prevStock = $this->partsInfo[$part]["stocks"][$prevDate];
            $curConsectDmd = $this->getPartAccuDayDmdFrom($part, $prevDate);
            
            $partAccuDmdMap[$part] = $curConsectDmd - $prevStock;

        }
        
        arsort($partAccuDmdMap);
        
        return array_keys($partAccuDmdMap);
    }
    

    
    /**
     * 计算从某日之后,根据指定日期长度的累计862需求量
     * @param unknown $part
     * @param unknown $fromDate
     * @param int $len : 默认为安全库存长度 - 1
     * @return void|mixed
     */
    protected function getPartAccuDayDmdFrom ($part, $fromDate, $len = null)
    {
        if (!$this->isDayDate($fromDate)) {
            // 如果是非862类型日期，返回null
            return;
        }
        
        if (empty($len)) {
            $len = $this->saftyDayLength - 1;
        }
        
        if (!isset($this->partsInfo[$part]["consectAccuDmds"][$fromDate])) {
            // 根据安全库存日期长度计算连续累计库存需求量
            $consectiveDmds = self::getSubArrayFromKey($this->partsInfo[$part]["dmds"], $fromDate, $len, true);
            $consectiveAccuDayDmd = 0;
 
            // 只考虑862需求类型的需求量和第一个830周分解后的需求
            foreach ($consectiveDmds as $date => $dmdQty) {
                if ($this->isDayDate($date)) {
                    $consectiveAccuDayDmd += $dmdQty;
                } else if ($len >= 1 && $date == $this->activeWeekDates[0]) {
                    // 将开始的830日期进行分解成6天日需求,将前几日当做日需求来补足连续需求量
                    $consectiveAccuDayDmd += floor($dmdQty / 6) * $len;
                    break;
                }
                $len--;
            }
            $this->partsInfo[$part]["consectAccuDmds"][$fromDate] = $consectiveAccuDayDmd;
        }
        
        return  $this->partsInfo[$part]["consectAccuDmds"][$fromDate];
    }
    
    
    protected function getLastExistingDmdDate ($date)
    {
        $orgDate = $this->getOrgDate();
        //  只考虑初始日期之后，并且是862类型的日期
        if ($date <= $orgDate || !$this->isDayDate($date)) {
            return;
        }
        
        while ($date > $orgDate) {
           $date = \DateHelper::getDateBefore($date);
           
           if ($this->totalDayProds[$date] && empty($this->totalDayDmds[$date])) {
               return false;
           }
           
           if ($this->totalDayDmds[$date]) {
               break;
           }
        }
        
        return $date;
        
    }
    
 
    protected function addPartProdOfDate ($part, $date, $netProd) 
    {
        $this->partsInfo[$part]["prods"][$date] += $netProd;
        $this->availDayCapacities[$date] -= $netProd;
    }

    
    public function DoAssemblyMrp ()
    {
        if (empty($this->partsInfo)) {
            return;
        }
        
        // 将优选共线项目先排产，并将对应的862生产日从本项目可工作日历中移除
        if ($this->preferentialCommonProjs) {
            foreach ($this->preferentialCommonProjs as $proj) {
                $preferedProjAssyProdModel = new self($this->_site, $proj);
                $preferedProjAssyProdModel->DoAssemblyMrp();
                $preferedProjProds = $preferedProjAssyProdModel->getTotalDayProds();
                foreach ($preferedProjProds as $prdate => $tprod) {
                    if ($preferedProjAssyProdModel->isDayDate($prdate) && $tprod) {
                        $this->isOccupiedDateMap[$prdate] = true;
                    }
                }
            }
        }
       
        
        
        $allParts = array_keys($this->partsInfo);
        $mrpedParts = $this->getMrpedParts();
        $unmrpedParts = $this->getUnmrpedParts();
        $dmdedUnmrpedParts = $this->getDmdedUnmrpedParts();
        $undmdedUnmrpedParts = $this->getUndmdedUnmrpedParts();
        $sortedDmdedUnmrpedParts = $this->getOrderedPartsByOrdMin($dmdedUnmrpedParts);

        $minOrdMin = null;
        $maxOrdMin = null;
        
        
        foreach ($dmdedUnmrpedParts as $part) {
            if (is_null($minOrdMin)) {
                $minOrdMin = $this->partsInfo[$part]["ordMin"];
            } else {
                if ($this->partsInfo[$part]["ordMin"] < $minOrdMin) {
                    $minOrdMin = $this->partsInfo[$part]["ordMin"];
                }
            }
        
            if (is_null($maxOrdMin)) {
                $maxOrdMin = $this->partsInfo[$part]["ordMin"];
            } else {
                if ($this->partsInfo[$part]["ordMin"] > $minOrdMin) {
                    $maxOrdMin = $this->partsInfo[$part]["ordMin"];
                }
            }
        }
        
        $startActiveDate = $this->getStartActiveDate();
        $start2ActiveDate = \DateHelper::getDateAfter($startActiveDate);
        // 先对862的需求进行MRP运算
        foreach ($this->activeDayDates as $curDate) {
            $curIndex = array_keys($this->activeDayDates, $curDate)[0];
            $pastDates = array_slice($this->activeDayDates, 0, $curIndex + 1);
            
            // 先对已进行mrp的零件进行产能扣减
            foreach ($mrpedParts as $part) {
                $this->availDayCapacities[$curDate] -= $this->partsInfo[$part]["prods"][$curDate];
            }
            
            if (empty($dmdedUnmrpedParts)) {
                continue;
            }
            
            
            $curIndex = array_keys($this->activeDayDates, $curDate)[0];
            $pastDates = array_slice($this->activeDayDates, 0, $curIndex + 1);
            
            $prevDate = \DateHelper::getDateBefore($curDate);
            $prev2Date = \DateHelper::getDateBefore($prevDate);
            
            
            

 
            
 
            // 如有必要，先进行最重要的规则：最小安全库存覆盖数排产
            if ($this->useSaftyStockRule) {  
                // 按照当前需求日起的覆盖库存需求数，来确定各配置零件的排产的优先级
                $unmrpedParts = $this->getUnmrpedPartsByPredictedProdPriority($curDate, $this->saftyDayLength);
                foreach ($unmrpedParts as $part) {
                    $class = $this->partsInfo[$part]["class"];

                    $prevOuterStock = $this->partsInfo[$part]["outerStocks"][$prevDate];
                    $prevOverallStock = $this->partsInfo[$part]["stocks"][$prevDate];
                    $curDmd = $this->partsInfo[$part]["dmds"][$curDate];
                    $preProd = $this->partsInfo[$part]["prods"][$curDate];   // 此时当前日期生产量，在之前未进行过其他规则排产（如参考上一需求日排产）时，将总是为0.
                
                    // 根据安全库存日期长度计算累计库存需求量
                    $consectiveDmdsAccu = $this->getPartAccuDayDmdFrom($part, $curDate);
                
                    // 当前日期是可生产日(而不能只是有需求的可生产日，否则极端情况下会导致死循环)，且该零件有总需求(此时才有必要排计划)，才考虑“补充”进行最小生产性生产，来保证库存满足N天连续需求日覆盖
                    if ( $this->isWorkday($curDate) && $this->partsInfo[$part]["hasDmds"]) {
                        $netDmd = $consectiveDmdsAccu + $curDmd - $preProd - $prevOverallStock;

                        if ($netDmd > 0) {
                            // 本次需求覆盖所安排的生产量必须累加（而不是直接设置）到'可生产日'的原生产量上去
                            // 某零件当日的第一次额外最小量排产，设置回溯标志为false
                            $this->arrangeMinExtraAssyProduction($part, $curDate, $netDmd, false);
                        }
                    }
                
                
                
                    // 由于生产日可能一次或多次向前安排，每个零件每个活动天进行计划安排后都必须重新计算之前每天包括当天的库存结余
                    $this->calculatePartStocksBetween($part, $pastDates);
                
                }
                 
            }
            


            
            
            

            // 如有必要，再进行产能排满
            if ($this->useComplement) {
                $this->complementDayCapacity($curDate);
                
                foreach ($unmrpedParts as $part) {
                    $this->calculatePartStockOfDate($part, $curDate);
                }
            }
            
            foreach ($this->partsInfo as $part => $info) {
                $this->calculatePartStockOfDate($part, $curDate);
            }
            
        }
 
            
        // 针对可能被反推至原本无生产需求生产日的特例，重新对所有日期进行一次产能反补
        // 但被反补的物料，会导致之后日期的生产需求本应该被降低，但实际却保持没降低之前的生产量。
        if ($this->useComplement) {
            foreach ($this->activeDayDates as $curDate) {
                $this->complementDayCapacity($curDate);
            }
        }
        
        
        // 为防万一，重新计算每个零件每个活动862日库存结余
        foreach ($allParts as $part) {
            $this->calculatePartStocksBetween($part, $this->activeDayDates); 
        }
        
        // 先对862的需求进行MRP运算
        $periodDates = array_merge($this->activeWeekDates, $this->activeMonthDates);
        foreach ($periodDates as $curDate) {
            foreach ($unmrpedParts as $part) {
                $ordMin = $this->partsInfo[$part]["ordMin"];
 
                $this->partsInfo[$part]["prods"][$curDate] = ceil($this->partsInfo[$part]["dmds"][$curDate] / $ordMin) * $ordMin;
                
                $this->calculatePartStockOfDate($part, $curDate);
            }
            
            foreach ($mrpedParts as $part) {
                $ordMin = $this->partsInfo[$part]["ordMin"];
                if (empty($this->partsInfo[$part]["prods"][$curDate])) {
                    $this->partsInfo[$part]["prods"][$curDate] =  ceil($this->partsInfo[$part]["dmds"][$curDate] / $ordMin) * $ordMin;
                }
            }

        }
        
        
        $this->recalculateTotalDayProds();
        $this->calculateAllPartsStock();
        

    }
    
 

    /**
     * @param unknown $part
     * @param unknown $prodDate
     * @param unknown $curNetDmd
     * @param bool $isBackdate
     */
    protected function arrangeMinExtraAssyProduction($part, $prodDate, $curNetDmd, $isBackdate)
    {
        $ordMin = $this->partsInfo[$part]["ordMin"];
        $curNetDmdForPallets = ceil($curNetDmd / $ordMin) * $ordMin; 
        
        $leftCapacity = $this->availDayCapacities[$prodDate];
        


        if ($leftCapacity >= $curNetDmdForPallets) {
            // 如果'生产日'剩余产能大于等于该零件的当前净整托需求，可以安排满足全额净托需求的生产
            $this->addPartProdOfDate($part, $prodDate, $curNetDmdForPallets);
        } else if ($prodDate == $this->getStartActiveDate()) {
            //  否则，如果生产日是第一活动日，没办法向前递推，只能安排在该天超额生产
            $this->addPartProdOfDate($part, $prodDate, $curNetDmdForPallets);
        } else  {
            // 否则如果还有产能
            
            if ($this->allowOverCapacityInMinPallets) {
                // 对于可以以刚刚好的整托数超过产能的项目，尽量在刚刚超过产能的条件下生产更多的托数
                
                $prodAmount = ceil($leftCapacity / $ordMin) * $ordMin;
                $this->addPartProdOfDate($part, $prodDate, $prodAmount);
                
                // 只是仍然可能残存一定的净需求
                $curNetDmd -= $prodAmount;
            } else {
                // 对于总是不可以超过产能的项目
                
                if ($leftCapacity >= $ordMin) {
                    // 此时，如果剩余产能尚且能至少支持一托生产，则尽量在不超过产能的条件下生产更多的托数
                    $prodAmount = floor($leftCapacity / $ordMin) * $ordMin;
                    $this->addPartProdOfDate($part, $prodDate, $prodAmount);
                
                    // 只是仍然可能残存一定的净需求
                    $curNetDmd -= $prodAmount;
                } else {
                    // 否则，连一托数的剩余产能都不足，则本日不进行生产了
                }
            }

            // 然后，还需将可能残存的需求部分，移到前一个"可生产日"进行生产(该步骤可递归进行)
            $preProdDate = $this->getClosestWorkdayDate($prodDate, false);
            //var_dump($prodDate, $preProdDate);
            // 当某零件进行当日的额外最小量回溯排产，设置回溯标志为true,这部分回溯的需求量，是强制性的。
 
            $this->arrangeMinExtraAssyProduction($part, $preProdDate, $curNetDmd, true);
            

        }
    }
    
    
    /**
     * @param unknown $part
     * @param unknown $prodDate
     * @param unknown $curNetDmd
     */
    public function arrangeLeadDayProduction($part, $prodDate, $curNetDmd, $isBack)
    {
        $ordMin = $this->partsInfo[$part]["ordMin"];
        $curNetDmdForPallets = ceil($curNetDmd / $ordMin) * $ordMin;
    
        $leftCapacity = $this->availDayCapacities[$prodDate];
    
        if ($leftCapacity >= $curNetDmdForPallets) {
            $this->addPartProdOfDate($part, $prodDate, $curNetDmdForPallets);
        } else if ($prodDate == $this->getStartActiveDate()) {
            $this->addPartProdOfDate($part, $prodDate, $curNetDmdForPallets);
        } else  {
    
            if ($leftCapacity >= $ordMin) {
                $prodAmount = floor($leftCapacity / $ordMin) * $ordMin;
                $this->addPartProdOfDate($part, $prodDate, $prodAmount);
                $curNetDmd -= $prodAmount;
            }
    
            $preProdDate = $this->getClosestWorkdayDate($prodDate, false);
            $this->arrangeLeadDayProduction($part, $preProdDate, $curNetDmd, true);
        }
    }
    
    
    /**
     * 
     * 对已应用过其他规则的当日生产的所有零件，进行产能补充
     * @param unknown $date
     */
    protected function complementDayCapacity ($date)
    { 
        // 需要进行一次当日生产量累计计算,只有当日有最小生产需求时(原先的<在工作日且有客户总需求的>条件下，可能会导致爆仓)，才进行产能补充
        $this->calculateCurrentTotalPartsProdOfDate($date);
        if (!$this->isMinProdRequiredDay($date)) {
            return;
        }
        
        

        
        $partsForComplement = $this->getPartsComplementPriorityByDate($date);
        
        //var_dump($date, $partsForComplement);
        
        if (!empty($partsForComplement)) {
            $minOrdMin = $maxOrdMin = null;
            foreach ($partsForComplement as $part) {
                if (is_null($minOrdMin)) {
                    $minOrdMin = $maxOrdMin = $this->partsInfo[$part]["ordMin"];
                } else {
                    if ($this->partsInfo[$part]["ordMin"] < $minOrdMin) {
                        $minOrdMin = $this->partsInfo[$part]["ordMin"];
                    }
                    if ($this->partsInfo[$part]["ordMin"] > $minOrdMin) {
                        $maxOrdMin = $this->partsInfo[$part]["ordMin"];
                    }
                }
            }
            
            // 只有当日还有至少超过最小包装量的产能，或者虽少于最少包装量但允许以最小托数超出超能，循环进行补充
            while ($this->availDayCapacities[$date] >= $maxOrdMin 
                    || ($this->allowOverCapacityInMinPallets && $this->availDayCapacities[$date] > 0) 
                    ) {
                // 此处使用平衡算法，总是对当日目前安排生产量最少的零件进行有限排产
                $part = $this->getLeastDayProdPartBetween($date, $partsForComplement);

                $this->addPartProdOfDate($part, $date, $this->partsInfo[$part]["ordMin"]);
                
                // 零件当日被补充的总数需要记录下来，用于在可能发生的后个生产日超出产能时向前回溯判断要不要回溯生产的比较
                $this->partsInfo[$part]["complements"][$date] += $this->partsInfo[$part]["ordMin"];
                
            }
            
            while ($this->availDayCapacities[$date] >= $minOrdMin || ($this->allowOverCapacityInMinPallets && $this->availDayCapacities[$date] > 0)) {
                foreach ($partsForComplement as $part) {
                    if ($this->availDayCapacities[$date] >= $this->partsInfo[$part]["ordMin"]) {
                        $this->addPartProdOfDate($part, $date, $this->partsInfo[$part]["ordMin"]);
                    }
                }
            }
 
        }
    }
    
    
    /**
     * 根据初步最小生产量计算后的结果， 获取某日的产能补充作用的零件的优先级顺序。
     * 对某一日的所有 <需要重新mrp的>  <从该日期起还有后续N日需求的> <非B515安特来源零件的>的零件号进行排序，排序规则依次：
     * XXXXXXXX已安排生产的，统一在未安排生产的之前XXXXX(暂时弃用本规则)；
     * 库存量小的，在库存量大的之前
     * @param unknown $date
     */
    public function getPartsComplementPriorityByDate ($date)
    {
        $partStockMap = [];
        $partsStockMap = $produedPartStockMap = $notProducedPartStockMap = [];
        foreach ($this->partsInfo as $part => $partInfo) {
            if ($this->partsInfo[$part]["isAnte"]) {
                // B515的安特订单，在既定规则排产后，总是不需要进行产能补充
                continue;
            }
            
            if ($partInfo["isMrp"] && $this->hasDayDmdsFrom($part, $date, $this->saftyDayLength)) {
                $stock = $partInfo["stocks"][$date];
                if ($partInfo["prods"][$date]) {
                    $produedPartStockMap[$part] = $stock;
                } else {
                    $notProducedPartStockMap[$part] = $stock;
                }
                $partsStockMap[$part] = $stock;
            }
        }
        asort($produedPartStockMap);
        asort($notProducedPartStockMap);
        asort($partsStockMap);
        
        //return array_keys($produedPartStockMap);
        return array_keys($partsStockMap);
        //return array_merge(array_keys($produedPartStockMap), array_keys($notProducedPartStockMap));
    }
    
    protected function getRefLastPriorityOrderFrom ($date, array $parts)
    {
        
    }
    
    protected function getLeastDayProdPartBetween ($date, array $parts)
    {
        $ldpPart = '';
        $minProd = 0;
        foreach ($parts as $part) {
            if (empty($ldpPart) || $minProd > $this->partsInfo[$part]["prods"][$date]) {
                $ldpPart = $part;
                $minProd = $this->partsInfo[$part]["prods"][$date];
            } 
        }
        
        return $ldpPart;
    }
 
    
    protected function calculatePartStockOfDate ($part, $date)
    {
        if ($this->isDayDate($date)) {
            $prevDate = \DateHelper::getDateBefore($date);
        } else {
            $prevDate = self::getPrevArrayElement($this->activeDates, $date);
        }
        

        
        $pInnerstock = $this->partsInfo[$part]["innerStocks"][$prevDate];
        $pOuterstock = $this->partsInfo[$part]["outerStocks"][$prevDate];
        $pOverallStock = $this->partsInfo[$part]["stocks"][$prevDate];
        $pProd = $this->partsInfo[$part]["prods"][$prevDate];
        
        
        if ($this->_proj == 'c490' && $this->isDayDate($date)) {
            $this->partsInfo[$part]["stocks"][$date] = $pOverallStock + $pProd - $this->partsInfo[$part]["dmds"][$date];
            $this->partsInfo[$part]["innerStocks"][$date] = $pProd;
            $this->partsInfo[$part]["outerStocks"][$date] = $this->partsInfo[$part]["stocks"][$date] - $this->partsInfo[$part]["innerStocks"][$date];
            return;
        } else if ($this->_proj == 'cd391' && $this->isDayDate($date)) {
            $this->partsInfo[$part]["stocks"][$date] = $pOverallStock + $pProd - $this->partsInfo[$part]["dmds"][$date];
            $this->partsInfo[$part]["innerStocks"][$date] = $pProd;
            $this->partsInfo[$part]["outerStocks"][$date] = $this->partsInfo[$part]["stocks"][$date] - $this->partsInfo[$part]["innerStocks"][$date];
            return;
        } else if ($this->_proj == 'b515' && $this->isDayDate($date)) {
            if ($this->partsInfo[$part]["isAnte"]) {
                $this->partsInfo[$part]["stocks"][$date] = $pOverallStock + $this->partsInfo[$part]["prods"][$date] - $this->partsInfo[$part]["dmds"][$date];
                $this->partsInfo[$part]["outerStocks"][$date] = 0;
                $this->partsInfo[$part]["innerStocks"][$date] = $this->partsInfo[$part]["stocks"][$date];
            } else {
                $this->partsInfo[$part]["stocks"][$date] = $pOverallStock + $pProd - $this->partsInfo[$part]["dmds"][$date];
                $this->partsInfo[$part]["innerStocks"][$date] = $pProd;
                $this->partsInfo[$part]["outerStocks"][$date] = $this->partsInfo[$part]["stocks"][$date] - $this->partsInfo[$part]["innerStocks"][$date];
                
            }
            return;
        }
        
        
        $this->partsInfo[$part]["stocks"][$date] = $pOverallStock + $this->partsInfo[$part]["prods"][$date] - $this->partsInfo[$part]["dmds"][$date];
        
        if (!$this->isDayDate($date)) {
            return;
        }
        
        if ($this->_proj == '315a' || $this->partsInfo[$part]["isAnte"]) {
            $this->partsInfo[$part]["outerStocks"][$date] = 0;
            $this->partsInfo[$part]["innerStocks"][$date] = $this->partsInfo[$part]["stocks"][$date];
        } else {
            // 该条件会对外库满足累计需求的项目产生非预期结果，已弃用：只在有客户需求的工作日，或是该日已产生最小生产需求，才考虑进行移库(此时确保让当天内库数只等于当天生产数即可）
//             if (($this->isWorkday($date) && $this->isDmdDay($date)) || ($this->isMinProdRequiredDay($date))) {
//                 $this->partsInfo[$part]["innerStocks"][$date] = $this->partsInfo[$part]["prods"][$date];;
//             } else {
//                 $this->partsInfo[$part]["innerStocks"][$date] = $this->partsInfo[$part]["innerStocks"][$prevDate];
//                 $this->partsInfo[$part]["outerStocks"][$date] = $this->partsInfo[$part]["stocks"][$date] - $this->partsInfo[$part]["innerStocks"][$date];
//             }
            
            // 目前设定为总是进行移库
            $this->partsInfo[$part]["innerStocks"][$date] = $this->partsInfo[$part]["prods"][$date];
            
        }
        
        $this->partsInfo[$part]["outerStocks"][$date] = $this->partsInfo[$part]["stocks"][$date] - $this->partsInfo[$part]["innerStocks"][$date];
        
        
        
        
    }
    
    public function calculateAllPartsStock()
    {
        foreach (array_keys($this->getPartsInfo()) as $part) {
            $this->calculatePartStocksBetween($part, $this->getActiveDates());
        }
    }
    
    protected function calculatePartStocksBetween ($part, array $dates)
    {  
        sort($dates);
        
        foreach ($dates as $date)  {
            $this->calculatePartStockOfDate($part, $date);
 
        }
    }
    
    protected function calculateCurrentTotalPartsProdOfDate ($date)
    {
        $this->totalDayProds[$date] = 0;
        foreach ($this->partsInfo as $part => $info) {
            $this->totalDayProds[$date] += $info["prods"][$date];
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
    

    
    
    
    public function getClosestWorkdayDate($date, $allowCurrent = false)
    {
        if ($this->isWorkday($date) && $allowCurrent) {
            return $date;
        }
        
        if ($date == $this->getStartActiveDate()) {
            return $date;
        }
        
        do {
            $date = \DateHelper::getDateBefore($date);
            // 如果是第一活动天，强制作为最近工作日
            if ($date == $this->getStartActiveDate()) {
                break;
            }
        } while (!$this->isWorkday($date));
        
        return $date;
    }
    
 
 
    public function getAvailAssyDayCapacities ()
    {
        return $this->availDayCapacities;
    }
    
    
    /**
     * 每次重新计算当前规则应用后的每日总生产数
     */
    public function recalculateTotalDayProds ()
    {
//         $overallCapacities = $this->getAssyCapacityDateMap();
//         foreach ($this->availDayCapacities as $date => $availCapacity) {
//             $this->totalDayProds[$date] = $overallCapacities[$date] - $availCapacity;
//         }
        
        foreach ($this->activeDates as $date) {
            $this->totalDayProds[$date] = 0;
            foreach ($this->partsInfo as $part => $partInfo) {
                $this->totalDayProds[$date] += $partInfo["prods"][$date];
            }
        }
        
        
    }
    
    public function recalculateTotalDayProdOfDate ($date)
    {
        $this->totalDayProds[$date] = 0;
        foreach ($this->partsInfo as $part => $partInfo) {
            $this->totalDayProds[$date] += $partInfo["prods"][$date];
        }
    }
    
    
    protected function getMaxBoxesProdAmountLimitOfDate ($date)
    {

        $prevStock = 0;
        $prevDate = \DateHelper::getDateBefore($date);
        foreach ($this->getPartsInfo() as $part => $info) {
            $prevStock += $info["stocks"][$prevDate];
        }
        
        $prevBoxAmount = ceil($prevStock / $this->qtyPerBox);
        
        $limitAmount = ($this->totalBoxAmount - $prevBoxAmount) * $this->qtyPerBox;
        
        if ($limitAmount < 0) {
            $limitAmount = 0;
        }
        
        return $limitAmount;
        
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
    
        $headers = ["零件", "产线", "信息", "类型"];
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
    
            ->setCellValueExplicit("C" . $row, $partInfo["desc1"])
            ->setCellValueExplicit("C" . ($row + 1), $partInfo["desc2"])
            ->setCellValueExplicit("C" . ($row + 2), "初始内库库存: " . $partInfo["innerStocks"][$orgDate])
            ->setCellValueExplicit("C" . ($row + 3), "初始外库库存: " . $partInfo["outerStocks"][$orgDate])
            ->setCellValueExplicit("C" . ($row + 4), "包装量：" . $partInfo["ordMin"])
            ->setCellValueExplicit("C" . ($row + 5), "前日需求量：" . $partInfo["dmds"][$orgDate])
    
            ->setCellValueExplicit("D" . $row, "需求")
            ->setCellValueExplicit("D" . ($row + 1), "计划")
            ->setCellValueExplicit("D" . ($row + 2), "内库")
            ->setCellValueExplicit("D" . ($row + 3), "外库 ")
            ->setCellValueExplicit("D" . ($row + 4), "总结余")
            ->setCellValueExplicit("D" . ($row + 5), "累积量")
    
            ;
    
    
    
            // 逐条日期写入数量信息
            $prefix = '';
            $j = 'E';
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
        ->mergeCells("A$row:B$row")
        ->setCellValue("A$row", "总需求量")
        ->setCellValue("C$row", "前日需求量")
        ->setCellValue("D$row", $totDmds[$this->getOrgDate()]);
        $prefix = '';
        $j = 'E';
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
        ->mergeCells("A$row:D$row")
        ->setCellValue("A$row", "总生产数");
        $prefix = '';
        $j = 'E';
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
    
 
    

    
    public static function getPrevArrayElement(array $arr, $val)
    {
        $prev = false;
        foreach ($arr as $curVal) {
            if ($val == $curVal) {
                break;
            }
            $prev = $curVal;
        }
        
        return $prev;
    }
    
    /**
     * @param array $arr : array with keys in ascending order
     * @param string $startKey
     * @param int $len
     * @param boolean $excludingStart
     * @return string[]
     */
    public static function getSubArrayFromKey($arr, $startKey, $len = 999, $excludingStart = false)
    {
        $subArr = [];
        foreach ($arr as $key => $val) {
            if ($len == 0)  {
                break;
            }
            if ( ($excludingStart && $key > $startKey) || (!$excludingStart && $key >= $startKey) ) {
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
    
}