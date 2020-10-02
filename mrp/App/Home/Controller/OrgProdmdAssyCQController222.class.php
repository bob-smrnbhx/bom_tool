<?php

namespace Home\Controller;
use Think\Controller;
use Think\Model;

class ProdmdAssyCQController extends ProplanController
{
    private $_dmdModel;
    
    
    private $_proj;
    

    

    
    //protected $_maxConfig = 4; // or 2
    private $saftyDayLength = 4;

    

    protected $isWeekdayWorkableMap = [
    ];
    protected $isWorkdayDateMap = [];
    
    protected $dateTypeMap = [];

    
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
    protected $baseCommonConds = [
            'ptp_pm_code' => 'L',
            'ptp_status'  => 'AC'
    ];
    
    protected $projAssyOptions = [
            /*
             * 项目的特定SQL条件
             */
            "specConds" => [
                'c490' => [
                        'ptp_site'    => '6000',
                        'lnd_line'    => 'A60006',
                        //'sod_ship'    => 'BVT8C1'
                ],
                'cd391' => [
                        'ptp_site'    => '6000',
                        'lnd_line'    => 'A60002',
                        //'sod_ship'    => 'BVT8A1',
                ],
                'b515'  => [
                        'ptp_site'    => '6000',
                        'lnd_line'    => 'A60001',
                        //'sod_ship'    => 'BVT8A1',
                ],
                '315a'  => [
                        'ptp_site'    => '6000',
                        'lnd_line'    => 'A60005',
                        //'sod_ship'    => 'CHN03',
                ]
            ],
            
            /*
             * 项目的每班总大产能
             * */
            "shiftCapacity" => [
                'c490'  => 2600,
                "cd391" => 960,
                "b515"  => 1100,
                "315a"  => 720
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
                    'c490'  => [''],
                    "cd391" => [''],
                    "b515"  => [''],
                    "315a"  => ['']
            ],
            

            
            // the binary numbers indicating weekday work state, from Sunday to Saturday
            'weekdayWorkRules' => [
                    'c490'  => 0b0111111,
                    "cd391" => 0b0111111,
                    "b515"  => 0b0111111,
                    "315a"  => 0b0111111
            ],
            

            
            /*
             * 项目是否使用连续累计需求日安全库存覆盖规则
             * */
            "useSaftyStockRule" => [
                    'c490'  => true,
                    "cd391" => true,
                    "b515"  => true,
                    "315a"  => false  
            ],
            
            
            /*
             * 项目的生产提前期。
             * 一般而言，应用了连续累计需求日安全库存规则的项目，不需要再考虑提前期（0为不考虑）。
             * */
            "leadDayLen" => [
                    'c490'  => 0,
                    "cd391" => 0,
                    "b515"  => 0,
                    "315a"  => 1  
            ],
            
            /*
             * 项目的每日产量是否需要尽量排满产
             * */
            "useComplement" => [
                'c490'  => false,
                "cd391" => false,
                "b515"  => true,
                "315a"  => true
            ],
            
            /* 
             * 项目总生产数是否参考上一个工作日的总需求量
             * 一般，只有在项目的每日产量不需要排满产时，该值为true才有意义
             * */
            "prodRefLastWorkdayTotalDmd" => [
                "c490"  => true,
                "cd391" => true,
                "b515"  => false,
                "315a"  => false
            ],
            
            
            /*
             * 是否允许总生产数，以刚超过该日产能的最小托数数量，超出产能
             * */
            "allowOverCapacityInMinPallets" => [
                'c490'  => false,
                "cd391" => false,
                "b515"  => false,
                "315a"  => false
            ],
            
            /*
             * 是否使用当日外库库存，而不是当日总库存，来进行累计需求量的判断
             * */
            "useOuterStockForSafty" => [
                    'c490'  => false,
                    "cd391" => true,
                    "b515"  => true,
                    "315a"  => false
            ],
            
            
            "prodToOuterStockMovementDelayedWorkDay" => [
                    'c490'  => 1,
                    "cd391" => 2,
                    "b515"  => 2,
                    "315a"  => 1    // 315a 生产数移动规则待确认!!!!
            ],
           
            
            /*
             * 是否考虑项目的周转箱总数限制（存在才考虑），以及项目的总周转箱数和每箱产品数
             * */
            "boxLimitationOptions" => [
                'c490' => [
                    "totalBoxAmount"  => 3200,
                    "qtyPerBox"       => 4
                ]
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
    
    protected function getBaseConds ()
    {
        return $this->baseProjConds;
        
    }
    
    protected function getAllClasses ()
    {
        return $this->projAssyOptions["classes"][$this->_proj];
    }
    
    public function _initialize ()
    {
        $this->today = date("Y-m-d", time());
        //$this->today = '2017-05-21';
        

        if (isset($_REQUEST["proj"])) {
            $this->_proj = strtolower(trim($_REQUEST["proj"]));
        } else {
            $this->_proj = reset(array_keys($this->projAssyOptions["specConds"]));
        }
        
        if (!isset($this->projAssyOptions["specConds"][$this->_proj])) {
            throw new \Exception("invalid project name provided");
        }
        
        // set properties according to specified project
        $this->specConds = $this->projAssyOptions["specConds"][$this->_proj];
        $this->baseProjConds = array_merge($this->baseCommonConds, $this->specConds);
        $this->shiftCapacity = $this->projAssyOptions["shiftCapacity"][$this->_proj];
        $this->useSaftyStockRule = $this->projAssyOptions["useSaftyStockRule"][$this->_proj];
        $this->leadDayLen = $this->projAssyOptions["leadDayLen"][$this->_proj];
        $this->prodToOuterStockMovementDelayedWorkDay = $this->projAssyOptions["prodToOuterStockMovementDelayedWorkDay"][$this->_proj];
        $this->useComplement =  $this->projAssyOptions["useComplement"][$this->_proj];
        $this->prodRefLastExistingTotalDmd = $this->projAssyOptions["prodRefLastWorkdayTotalDmd"][$this->_proj];
        $this->allowOverCapacityInMinPallets = $this->projAssyOptions["allowOverCapacityInMinPallets"][$this->_proj];
        if (isset($this->projAssyOptions["boxLimitationOptions"][$this->_proj])) {
            $this->useBoxLimitation = true;
            $this->totalBoxAmount = $this->projAssyOptions["boxLimitationOptions"][$this->_proj]["totalBoxAmount"];
            $this->qtyPerBox = $this->projAssyOptions["boxLimitationOptions"][$this->_proj]["qtyPerBox"];
        }
        $this->useOuterStockForSafty = $this->projAssyOptions["useOuterStockForSafty"][$this->_proj];
        
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
        return $this->today;
    }
    
 
    
    
    /**
     * 获取所有活动需求日的前一天，作为初始前一天
     * @return string
     */
    public function getOrgDate ()
    {
        if (empty($this->orgDate)) {
            $this->orgDate = self::getDateBefore($this->getStartActiveDate());         
        }
        
        return $this->orgDate;
    }
    
//     /**
//      * 需要获取所有活动的需求日期，从最小到最大，中间任何一个可用日期都需要包含在内。
//      * 按照预定义的规则来获取
//      */
//     public function getActiveDates ()
//     {
//         if (empty($this->activeDates)) {
//             $conds = $this->getBaseConds();
//             $conds['dmd_date'] = ['EGT', $this->getStartActiveDate()];
    
//             $dates = $this->getDmdModel()
//             ->distinct(true)->field("dmd_date")
//             ->where($conds)->getField("dmd_date", true);
//             $dates =  array_unique($dates);
//             sort($dates);
            

//             $orgDate = $this->getOrgDate();
//             $this->dateTypeMap[$orgDate] =  'd';
//             // 认定从昨天开始的固定14天（即从今天开始的13天）都是862需求
            
//             $startDdate = $this->getStartActiveDate();
//             $endDDate = date("Y-m-d", strtotime($startDdate) + 86400 * ($this->daysLength - 2));
//             $this->activeDayDates = self::getDatesBetween($startDdate, $endDDate);
//             sort($this->activeDayDates);
//             foreach ($this->activeDayDates as $date) {
//                 $this->dateTypeMap[$date] =  'd';
//             }

            
//             // 认定之后的日期到最大日期都是830需求,最多只取固定的6个即可，且只取周一的日期。
//             foreach ($dates as $date) {
//                 if ($date > $endDDate && date("N", strtotime($date)) == 1) {
//                     $this->activeWeekDates[] = $date;
                    
//                     if (count($this->activeWeekDates) >= $this->weeksLength) {
//                         break;
//                     }
//                 }
//             }
//             sort($this->activeWeekDates);
//             foreach ($this->activeWeekDates as $date) {
//                 $this->dateTypeMap[$date] =  'w';
//             }
            
 
 
//             $this->activeDates = array_merge($this->activeDayDates, $this->activeWeekDates, $this->activeMonthDates);
 
//         }
    

//         return $this->activeDates;
//     }
    
    /**
     * 根据固定的规则，统一计算并获取所有的可用活动日期。
     * 按照预定义的规则来获取
     */
    public function getActiveDates ()
    {
        if (empty($this->activeDates)) {

            $orgDate = $this->getOrgDate();
            $this->dateTypeMap[$orgDate] =  'd';
            // 认定从昨天开始的固定14天（即从今天开始的13天）都是862需求
            
            // 认定从今天开始，直到第三个周一前，都是862需求日
            // 算法：从今天开始逐日递推，在遇到第三个周一前（今天如果为周一也包括在计数内）递推结束
            $startDdate = $this->getStartActiveDate();
            $mondayCount = 2;
            $date = $startDdate;
            if (self::isMonday($date)) {
                $mondayCount--;
            }
            while ($mondayCount >= 0) {
                $this->activeDayDates[] = $date;
                
                $date = self::getDateAfter($date);
                if (self::isMonday($date)) {
                    $mondayCount--;
                } 
            }
            
            foreach ($this->activeDayDates as $date) {
                $this->dateTypeMap[$date] =  'd';
            }
            
            // 认定从最后一个862需求日开始，之后的连续4个周一的日期，为830周需求日
            $endDdate = end($this->activeDayDates);
            $mondayCount = 4;
            $date = $endDdate;
            while ($mondayCount > 0) {
                $date = self::getDateAfter($date);
                if (self::isMonday($date)) {
                    $this->activeWeekDates[] = $date;
                    $mondayCount--;
                }
            }
 
            /// 将第一个830周一取出进行分解，每日子日期视为862需求日
            $this->initFirstWDate = array_shift($this->activeWeekDates);
 
            for ($i = 0; $i < 7; $i++) {
                $date = self::getDateAfter($this->initFirstWDate, $i);
                $this->activeDayDates[] = $date;
                $this->dateTypeMap[$date] =  'd';
            }
            
            
            foreach ($this->activeWeekDates as $date) {
                $this->dateTypeMap[$date] =  'w';
            }
            

            
            
            
            // 认定从最后一个862需求日开始，之后的连续三个月1的日期，为830周需求日
            $endWdate = end($this->activeWeekDates);
            $monthFirstdayCount = 3;
            $date = $endWdate;
            while ($monthFirstdayCount > 0) {
                $date = self::getDateAfter($date);
                if (self::isMonthFirstDay($date)) {
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
    

    
    /**
     * 获取所有日期是否是830日期的判断映射
     * @return boolean[]
     */
    protected function getAssyIsPeriodDateMap ()
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
    
    protected function getBelongedPeriodDate ($date)
    {
        if ($this->isDayDate($date)) {
            return;
        }
        $pddates = array_merge($this->activeWeekDates, $this->activeMonthDates);
        
        $psdate = $pedate = '';
        foreach ($pddates as $pddate) {
            if ($date >= $pddate) {
                $psdate = $pddate;
            } else {
                break;
            }
        }
        
        return $psdate;
    }
    
 
    
    /**
     * 判断日期是否是通用维护的工作日，不分配置类型进行通用判断
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
        return $this->isWeekdayWorkableMap[$wd];
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
            $date = self::getDateAfter($date, 1);
            
            
            if (!$this->isDayDate($date)) {
                // 如果碰到830，直接返回
                return $date;
            }
            
            
            if ($this->isWorkday($date)) {
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
    

    
    protected function getIsDoubleShiftDateMap ()
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
    
    protected function getCapacities ()
    {
        if (empty($this->dayCapacities)) {
            foreach ($this->activeDayDates as $date) {
                $wd = date("N", strtotime($date));
                $this->dayCapacities[$date] = $this->weekdayShiftsRules[$wd] * $this->shiftCapacity;
            }
        }
        
        return $this->dayCapacities;
    }
    

    protected function getIsWorkdayDateMap ()
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
            $conds = $this->getBaseConds();
            
            //         $conds["dmd_date"] = [
            //                 ['EGT', self::getDateBefore($this->getStartActiveDate())],
            //                 ['EXP', 'is null'],
            //                 'OR'
            //         ];
            //只获取在最近的库存日期之后的客户需求
            $conds["dmd_date"] = ['EGT', $this->orgDate];
            //$conds['dmd_type'] = 'd';
            
            $activeDates = $this->getActiveDates();
            $startActiveDate = $this->getStartActiveDate();
            $orgDate = $this->getOrgDate();            
            
            $result = $this->getDmdModel()->where($conds)->order("ptp_part")->select();
            


            foreach ($result as $row) { 
                $part = $row["ptp_part"];
                
                // 对B515部分既可来源于安特也可来源于福特的物料进行双重区分，使用双重规则。
                if ($this->_proj == 'b515' && in_array($part, ["04.02.17.0039", "04.02.17.0040"])) {
                    if ($row["sod_ship"] == '3264H1') {
                        $part .= "-ante";
                        $row["is_ante"] = true;
                    }  else {
                        $part .= "-ford";
                        $row["is_ante"] = false;
                    }
                }
                
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
                            'innerStocks' => [], // 每天都保存
                            'outerStocks' => [], // 每天都保存
                            'stocks'   => [],     // 每天都保存
                            'consectAccuDmds' => []
                    ];
                    
                    if ($this->_proj == '315a') {
                        $this->partsInfo[$part]["accuDmdOff"] = true;
                    }
                    
                    // 安特订单的专属包装量设置
                    if ($this->partsInfo[$part]["isAnte"]) {
                        $this->partsInfo[$part]["ordMin"] = 108;
                        $this->partsInfo[$part]["accuDmdOff"] = true;
                    }
                    
                    // 同时来源于安特和福特的物料，由于无法分别获取保存的客户需求，所以永远视为未mrp状态，始终重新mrp
                    if (isset($row["is_ante"])) {
                        $this->partsInfo[$part]["isMrp"] = true;
                    }
                }
                
                // 如果制造件不需要重新进行mrp，直接读取对应的生产计划数据即可
                if (!$this->partsInfo[$part]["isMrp"]) {
                    // 已有某日期的计划数据时才进行记录
                    if ($row['drps_date'] != null && !empty($row['drps_qty']) && !isset($this->partsInfo[$part]['prods'][$row['drps_date']])) {
                        $this->partsInfo[$part]['prods'][$row['drps_date']] =  floatval($row['drps_qty']);
                    }
                }
                
                // 将当日未进行任何生产前导出的库存数据（in_date字段仍然为当天)，视为昨日的最终库存
                
                if (!isset($this->partsInfo[$part]["innerStocks"][$orgDate]) && $row['in_date'] == $startActiveDate && $row["in_type"] == 'i') {
                    if ($this->partsInfo[$part]["isAnte"] && $row['in_tt'] == 'a') { 
                        $this->partsInfo[$part]["innerStocks"][$orgDate] = floatval($row["in_qty_oh"]);
                    } else if (!$this->partsInfo[$part]["isAnte"] && $row['in_tt'] != 'a') {
                        $this->partsInfo[$part]["innerStocks"][$orgDate] = floatval($row["in_qty_oh"]);
                    }
                    

                }
                
                if (!isset($this->partsInfo[$part]["outerStocks"][$orgDate]) && $row['in_date'] == $startActiveDate && $row["in_type"] == 'o') {
                    if ($this->partsInfo[$part]["isAnte"] && $row['in_tt'] == 'a') {
                        $this->partsInfo[$part]["outerStocks"][$orgDate] = floatval($row["in_qty_oh"]);
                    } else if (!$this->partsInfo[$part]["isAnte"] && $row['in_tt'] != 'a') {
                        $this->partsInfo[$part]["outerStocks"][$orgDate] = floatval($row["in_qty_oh"]);
                    }
                }
                
                
                if ($this->isDayDate($row["dmd_date"])) {
                    $bdate = $row["dmd_date"];
                } else {
                    // 对于830日期，准备进行多个可能的阶段日期累加。
                    $bdate = $this->getBelongedPeriodDate($row["dmd_date"]);
                }
                
                // 同个物料一个需求数，可能属于多个订单。每个830日期，可能包含多个需求日
                if (!isset($this->partsInfo[$part]['dmds'][$bdate][$row["sod_ship"]][$row["dmd_date"]])) {
                    if (($this->partsInfo[$part]["isAnte"] && $row["sod_ship"] == "3264H1") || (!$this->partsInfo[$part]["isAnte"] && $row["sod_ship"] != "3264H1")) {
                        
                        $this->partsInfo[$part]['dmds'][$bdate][$row["sod_ship"]][$row["dmd_date"]] = floatval($row['dmd_qty']);
                    }  
                    


                }
            }
            
            
            foreach ($this->partsInfo as $part => $partInfo) {
                // 叠加所有订单的862需求量和初始需求量
                foreach ($this->partsInfo[$part]['dmds'] as $bdate => &$shipDmds) {
                    foreach ($shipDmds as $ship => &$dmds) {
                        $dmds = array_sum($dmds);
                    }
                    $shipDmds = array_sum($shipDmds);
                }
 

                // 计算初始日期的实际外库库存和实际总库存量
                $this->partsInfo[$part]["outerStocks"][$orgDate] = $this->partsInfo[$part]["outerStocks"][$orgDate] - $this->partsInfo[$part]["dmds"][$orgDate];
                $this->partsInfo[$part]['stocks'][$orgDate] = $this->partsInfo[$part]["innerStocks"][$orgDate] + $this->partsInfo[$part]["outerStocks"][$orgDate];
                

                
                
                // 进行第一个830周分解
                $fwqty = $this->partsInfo[$part]["dmds"][$this->initFirstWDate];
                for ($i = 0; $i < 6; $i++) {
                    $date = self::getDateAfter($this->initFirstWDate, $i);
                    $this->partsInfo[$part]["dmds"][$date] = floor($fwqty / 6);
                }
                $this->partsInfo[$part]["dmds"][$date] += $fwqty % 6;
                
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
                    $this->getAccuDayDmdFrom($part, $date);
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

            
            $prevDate = self::getDateBefore($fromDate);
            $prevStock = $partInfo["stocks"][$prevDate];
            
            $consectPartDmd = $this->getAccuDayDmdFrom($part, $prevDate, $accuLength);
            
 
            
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
    protected function hasDayDmdsFrom($part, $date)
    {
        $lastActiveDayDate = end($this->activeDayDates);
        
        if ($this->isDayDate($date)) {
            if ($date < $lastActiveDayDate) {
                foreach ($this->activeDayDates as $curDate) {
                    if ($curDate >= $date && $this->partsInfo[$part]['dmds'][$curDate] != 0) {
                        return true;
                    }
                }
            } else if ($date == $lastActiveDayDate) {
                $startActiveWeekDate = reset($this->activeWeekDates);
                if ($this->partsInfo[$part]['dmds'][$startActiveWeekDate]) {
                    return true;
                }
            }
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
        foreach ($this->partsInfo as $partInfo) {
            if (!$partInfo["isMrp"]) {
                $parts[] = $partInfo["part"];
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

            $prevDate = self::getDateBefore($curDate);
            $prevStock = $this->partsInfo[$part]["stocks"][$prevDate];
            $curConsectDmd = $this->getAccuDayDmdFrom($part, $prevDate);
            
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
    protected function getAccuDayDmdFrom ($part, $fromDate, $len = null)
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
           $date = self::getDateBefore($date);
           
           if ($this->totalDayDmds[$date]) {
               break;
           }
        }
        
        return $date;
        
    }
    
 
    

    
    public function DoAssemblyMrp ()
    {
        if (empty($this->partsInfo)) {
            return;
        }
        
        $allParts = array_keys($this->partsInfo);
        $mrpedParts = $this->getMrpedParts();
        $unmrpedParts = $this->getUnmrpedParts();
        $dmdedUnmrpedParts = $this->getDmdedUnmrpedParts();
        $undmdedUnmrpedParts = $this->getUndmdedUnmrpedParts();
        $sortedDmdedUnmrpedParts = $this->getOrderedPartsByOrdMin($dmdedUnmrpedParts);


        // 先对862的需求进行MRP运算
        foreach ($this->activeDayDates as $curDate) {
            // 先对已进行mrp的零件进行产能扣减
            foreach ($mrpedParts as $part) {
                $this->availDayCapacities[$curDate] -= $this->partsInfo[$part]["prods"][$curDate];
            }
            
            if (empty($dmdedUnmrpedParts)) {
                continue;
            }
            
            $curIndex = array_keys($this->activeDayDates, $curDate)[0];
            $pastDates = array_slice($this->activeDayDates, 0, $curIndex + 1);
            $prevDate = self::getDateBefore($curDate);
            
            
            // 首先确保每个物料的每日结余都必须大于等于最小包装量
            if ($this->isWorkday($curDate)) {
                foreach ($unmrpedParts as $part) {
                    $prevDate = self::getDateBefore($curDate);
                    $prevOverallStock = $this->partsInfo[$part]["stocks"][$prevDate];
                    $curDmd = $this->partsInfo[$part]["dmds"][$curDate];
                    $ordMin = $this->partsInfo[$part]["ordMin"];
                    
                    $netDmd = $ordMin + $curDmd - $prevOverallStock;

                    if ($netDmd > 0) {
                        $this->partsInfo[$part]["prods"][$curDate] += ceil($netDmd / $ordMin) * $ordMin;
                        $this->availDayCapacities[$curDate] -= ceil($netDmd / $ordMin) * $ordMin;
                    }

                }
            }
            

            

 
            
 
            // 如有必要，先进行最重要的规则：最小安全库存覆盖数排产
            if ($this->useSaftyStockRule) {  
                // 按照当前需求日起的覆盖库存需求数，来确定各配置零件的排产的优先级
                $unmrpedParts = $this->getUnmrpedPartsByPredictedProdPriority($curDate, $this->saftyDayLength);
                foreach ($unmrpedParts as $part) {
               
                    // B515的安特来源物料不考虑安全库存覆盖，只考虑将后一天需求提前即可
                    if ($this->partsInfo[$part]["isAnte"] && $this->isWorkday($curDate) && $this->partsInfo[$part]["hasDmds"]) {
                        $nextDate = $curDate;
                        do {
                            $nextDate = self::getDateAfter($nextDate);
                        } while (!$this->isWorkday($nextDate));
                        
                        if ($this->isDayDate($nextDate)) {
                            $delta = $this->partsInfo[$part]["dmds"][$nextDate] - $this->partsInfo[$part]["prods"][$curDate];
                            if ($delta > 0) {
                                $this->partsInfo[$part]["prods"][$curDate] += $delta;
                                $this->availDayCapacities[$curDate] -= $delta;
                            }

                        }
                        
                        $this->calculatePartStockOfDate($part, $curDate);
                        continue;
                    }

                    
                    
                    
                    $class = $this->partsInfo[$part]["class"];
                
                    $prevDate = self::getDateBefore($curDate);
                    if ($prevDate == $this->getOrgDate()) {
                        $prevProd = $this->partsInfo[$part]["innerStocks"][$prevDate];
                    } else {
                        $prevProd = $this->partsInfo[$part]["prods"][$prevDate];
                    }
                
                    $prevOuterStock = $this->partsInfo[$part]["outerStocks"][$prevDate];
                    $prevOverallStock = $this->partsInfo[$part]["stocks"][$prevDate];
                    $curDmd = $this->partsInfo[$part]["dmds"][$curDate];
                    $curProd = $this->partsInfo[$part]["prods"][$curDate];   // 此时当前日期生产量，在之前未进行过其他规则排产（如参考上一需求日排产）时，将总是为0.
                
                    // 根据安全库存日期长度计算累计库存需求量
                    $consectiveDmdsAccu = $this->getAccuDayDmdFrom($part, $curDate);
                
                    // 当前日期是可生产日(而不能只是有需求的可生产日，否则极端情况下会导致死循环)，且该零件有总需求(此时才有必要排计划)，才考虑“补充”进行最小生产性生产，来保证库存满足N天连续需求日覆盖
                    if ($this->isWorkday($curDate) && $this->isDmdDay($curDate) && $this->partsInfo[$part]["hasDmds"]) {
                        if ($this->useOuterStockForSafty) {
                            $netDmd = $consectiveDmdsAccu + $curDmd - $curProd - $prevOuterStock - $prevProd;
                        } else {
                            $netDmd = $consectiveDmdsAccu + $curDmd - $curProd - $prevOverallStock;
                        }
                
                        
                
                        // 只要某日的累计净需求量多于上一次的库存结余，就必须安排生产
                        if ($netDmd > 0) {
                            // 本次需求覆盖所安排的生产量必须累加（而不是直接设置）到'可生产日'的原生产量上去
                            // 某零件当日的第一次额外最小量排产，设置回溯标志为false
                            $this->arrangeMinExtraAssyProduction($part, $curDate, $netDmd, false);
                        }
                    }
                
                
                
                    // 由于生产日可能一次或多次向前安排，每个零件每个活动天进行计划安排后都必须重新计算之前每天包括当天的库存结余
                    $curIndex = array_keys($this->activeDayDates, $curDate)[0];
                    $pastDates = array_slice($this->activeDayDates, 0, $curIndex + 1);
                    $this->calculatePartStocksBetween($part, $pastDates);
                
                }
                 
            }
            
            // 如有必要，再进行上一个需求日的参考量排产

            if ($this->prodRefLastExistingTotalDmd) {
                $lastExistingDmdDate = $this->getLastExistingDmdDate($curDate);
                $initAlt = !$initAlt;
                
                
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
                
                if ($this->totalDayDmds[$lastExistingDmdDate] && $this->isWorkday($curDate) && $this->isDmdDay($curDate)) {

                    if ($this->_proj == 'c490') {
                        $alt = $initAlt;
 
                        foreach ($sortedDmdedUnmrpedParts as $part) {
                            if ($this->availDayCapacities[$curDate] > $maxOrdMin && 
                                   (
                                    ($alt && $this->totalDayProds[$curDate] > $this->totalDayDmds[$lastExistingDmdDate] - $minOrdMin)
                                    || 
                                    (!$alt && $this->totalDayProds[$curDate] > $this->totalDayDmds[$lastExistingDmdDate] + $minOrdMin)
                                    ) 
                                    )
                                   {
                                break;
                            }
                            
                            $ordMin = $this->partsInfo[$part]["ordMin"];
                            
                            $maxPA = ceil($this->partsInfo[$part]["dmds"][$lastExistingDmdDate] / $ordMin);
                            $minPA = floor($this->partsInfo[$part]["dmds"][$lastExistingDmdDate] / $ordMin);

                            
                            
                            if ($alt) {
                                $palletsAmount = $maxPA;
                            } else {
                                $palletsAmount = $minPA;
                            }

                            $lastDmdDateAmountOfPallets = $palletsAmount * $ordMin;
                            $alt = !$alt;

                            
                            if ($this->partsInfo[$part]["prods"][$curDate] < $lastDmdDateAmountOfPallets) {
                                $delta = $lastDmdDateAmountOfPallets - $this->partsInfo[$part]["prods"][$curDate];
                                $this->partsInfo[$part]["prods"][$curDate] += $delta;
                                $this->availDayCapacities[$curDate] -= $delta;
                            }
                            
                            $this->calculateCurrentTotalPartsProdOfDate($curDate);
                        }
                    }  else if ($this->_proj == 'cd391') {
                        
                        foreach ($dmdedUnmrpedParts as $part) {
                            if ($this->availDayCapacities[$curDate] < $maxOrdMin) {
                                break;
                            }
    
                            // 当日排产数参考上一个需求存在日的需求数的整托数（
                            // ※※※暂时设定为统一不超过前需求数，作为初步排产（否则(使用ceil())，将会发现，很有可能严重超出产能!!!）
                            $ordMin = $this->partsInfo[$part]["ordMin"];
                            $palletsAmount = floor($this->partsInfo[$part]["dmds"][$lastExistingDmdDate] / $ordMin);
    
    
                            // ※※※当上一个需求存在日有需求，则至少本日排产1箱（该规则是否合适存疑!!!）
                            // 经验证，该规则可能导致部分每日小批量需求而每拖数偏大的物料排产后库存爆炸!!!
                            if ($this->partsInfo[$part]["dmds"] && $palletsAmount == 0) {
                                $palletsAmount = 1;
                            }
    
                            $lastDmdDateAmountOfPallets = $palletsAmount * $ordMin;
    
    
                            if ($this->partsInfo[$part]["prods"][$curDate] < $lastDmdDateAmountOfPallets) {
                                $delta = $lastDmdDateAmountOfPallets - $this->partsInfo[$part]["prods"][$curDate];
                                $this->partsInfo[$part]["prods"][$curDate] += $delta;
                                $this->availDayCapacities[$curDate] -= $delta;
                            }
                        }
                        
                        while ($this->availDayCapacities[$curDate] > $maxOrdMin && $this->totalDayDmds[$lastExistingDmdDate] - $this->totalDayProds[$curDate] - $compensation >= $minOrdMin) {
                            $curPart = $this->getLeastDayProdPartBetween($curDate, $dmdedUnmrpedParts);
                    
                            $this->partsInfo[$curPart]["prods"][$curDate] += $this->partsInfo[$curPart]["ordMin"];
                            $this->availDayCapacities[$curDate] -= $this->partsInfo[$curPart]["ordMin"];
                    
                            $this->calculateCurrentTotalPartsProdOfDate($curDate);
                        }
                        $compensation = $this->totalDayDmds[$curDate] - $this->totalDayDmds[$lastExistingDmdDate];
                    }
                    
                    
//                     foreach ($dmdedUnmrpedParts as $part) {

//                         // 当日排产数参考上一个需求存在日的需求数的整托数（
//                         // ※※※暂时设定为统一不超过前需求数，作为初步排产（否则(使用ceil())，将会发现，很有可能严重超出产能!!!）
//                         $ordMin = $this->partsInfo[$part]["ordMin"];
//                         $palletsAmount = floor($this->partsInfo[$part]["dmds"][$lastExistingDmdDate] / $ordMin);
                        
                        
//                         // ※※※当上一个需求存在日有需求，则至少本日排产1箱（该规则是否合适存疑!!!）
//                         // 经验证，该规则可能导致部分每日小批量需求而每拖数偏大的物料排产后库存爆炸!!!
//                         if ($this->partsInfo[$part]["dmds"] && $palletsAmount == 0) {
//                             $palletsAmount = 1;
//                         }
                        
//                         $lastDmdDateAmountOfPallets = $palletsAmount * $ordMin;
                        
                        
//                         if ($this->partsInfo[$part]["prods"][$curDate] < $lastDmdDateAmountOfPallets) {
//                             $delta = $lastDmdDateAmountOfPallets - $this->partsInfo[$part]["prods"][$curDate];
//                             $this->partsInfo[$part]["prods"][$curDate] += $delta;
//                             $this->availDayCapacities[$curDate] -= $delta;
//                         }
            
            
//                     }
            
//                     // 再设定一个均衡算法，部分可超出，部分不超出，从而让总生产量接近前需求存在日总需求量(这一步是否可以考虑在保证最小安全库存后再做?)

//                     $minOrdMin = null;
//                     $maxOrdMin = null;
//                     foreach ($dmdedUnmrpedParts as $part) {
//                         if (is_null($minOrdMin)) {
//                             $minOrdMin = $this->partsInfo[$part]["ordMin"];
//                         } else {
//                             if ($this->partsInfo[$part]["ordMin"] < $minOrdMin) {
//                                 $minOrdMin = $this->partsInfo[$part]["ordMin"];
//                             }
//                         }
                    
//                         if (is_null($maxOrdMin)) {
//                             $maxOrdMin = $this->partsInfo[$part]["ordMin"];
//                         } else {
//                             if ($this->partsInfo[$part]["ordMin"] > $minOrdMin) {
//                                 $maxOrdMin = $this->partsInfo[$part]["ordMin"];
//                             }
//                         }
//                     }
                    
//                     $this->calculateCurrentTotalPartsProdOfDate($curDate);
                    
//                     if ($this->_proj == 'cd391') {
//                         while ($this->availDayCapacities[$curDate] > $minOrdMin &&  $this->totalDayDmds[$lastExistingDmdDate] - $this->totalDayProds[$curDate] >= $minOrdMin) {
//                             $curPart = $this->getLeastDayProdPartBetween($curDate, $dmdedUnmrpedParts);
//                             $this->partsInfo[$curPart]["prods"][$curDate] += $this->partsInfo[$curPart]["ordMin"];
//                             $this->availDayCapacities[$curDate] -= $this->partsInfo[$curPart]["ordMin"];
//                             $this->calculateCurrentTotalPartsProdOfDate($curDate);
//                         }
//                     } else if ($this->_proj == 'c490') {
//                         var_dump($curDate, $compensation);
//                         while ($this->availDayCapacities[$curDate] > $maxOrdMin && $this->totalDayDmds[$lastExistingDmdDate] - $this->totalDayProds[$curDate] - $compensation >= $minOrdMin) {
//                             $curPart = $this->getLeastDayProdPartBetween($curDate, $dmdedUnmrpedParts);
                    
//                             $this->partsInfo[$curPart]["prods"][$curDate] += $this->partsInfo[$curPart]["ordMin"];
//                             $this->availDayCapacities[$curDate] -= $this->partsInfo[$curPart]["ordMin"];
                    
//                             $this->calculateCurrentTotalPartsProdOfDate($curDate);
//                         }
//                         $compensation = $this->totalDayDmds[$curDate] - $this->totalDayDmds[$lastExistingDmdDate];
                    
                    
//                     } else {
                    
//                     }
            
                    foreach ($unmrpedParts as $part) {
                        $this->calculatePartStockOfDate($part, $curDate);
                    }
                }
            }
            
            
            
            
 
            
            
                
            // 如有必要，再进行生产提前期确保排产
            if ($this->leadDayLen >= 1) {  
                $prevDate = self::getDateBefore($curDate);

                foreach ($dmdedUnmrpedParts as $part) {
                    if ($this->isWorkday($curDate)) {
                        $prevStock = $this->partsInfo[$part]["stocks"][$prevDate];
                        $curDmd = $this->partsInfo[$part]["dmds"][$curDate];
                        $curProd = $this->partsInfo[$part]["prods"][$curDate];
                        $relatedDmd = $this->getDmdAfterLeadedDay($part, $curDate);
                        $curNetDmd = $curDmd + $relatedDmd - $curProd - $prevStock ;
                        
                        
                        if ($curNetDmd > 0) {
                            $this->arrangeLeadDayProduction($part, $curDate, $curNetDmd);
                        }
                    }
                    
                    $this->calculatePartStockOfDate($part, $curDate);
 
                }
            }
            
            
            // 如有必要，再进行产能排满
            if ($this->useComplement) {
                $this->complementDayCapacity($curDate);
                $this->calculatePartStockOfDate($part, $curDate);
            }
            
            foreach ($this->partsInfo as $part => $info) {
                $this->calculatePartStockOfDate($part, $curDate);
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
                
//                 $prevDate = self::getPrevArrayElement($this->activeDates, $curDate);
//                 $netDmd = $this->partsInfo[$part]["dmds"][$curDate] - $this->partsInfo[$part]["stocks"][$prevDate];
//                 if ($netDmd > 0) {
//                     $this->partsInfo[$part]["prods"][$curDate] = ceil($netDmd / $ordMin) * $ordMin;
//                 }
                $this->partsInfo[$part]["prods"][$curDate] = ceil($this->partsInfo[$part]["dmds"][$curDate] / $ordMin) * $ordMin;
                
                $this->calculatePartStockOfDate($part, $curDate);
            }
        }
        
        $this->recalculateTotalAssyDayProds();
        

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
            $this->partsInfo[$part]["prods"][$prodDate] += $curNetDmdForPallets;
            $this->availDayCapacities[$prodDate] -= $curNetDmdForPallets;  
        } else if ($prodDate == $this->getStartActiveDate()) {
            //  否则，如果生产日是第一活动日，没办法向前递推，只能安排在该天超额生产
            $this->partsInfo[$part]["prods"][$prodDate] += $curNetDmdForPallets;
            $this->availDayCapacities[$prodDate] -= $curNetDmdForPallets;  
        } else  {
            // 否则如果还有产能
            
            if ($this->allowOverCapacityInMinPallets) {
                // 对于可以以刚刚好的整托数超过产能的项目，尽量在刚刚超过产能的条件下生产更多的托数
                
                $prodAmount = ceil($leftCapacity / $ordMin) * $ordMin;
                $this->partsInfo[$part]["prods"][$prodDate] += $prodAmount;
                $this->availDayCapacities[$prodDate] -= $prodAmount;
                
                // 只是仍然可能残存一定的净需求
                $curNetDmd -= $prodAmount;
            } else {
                // 对于总是不可以超过产能的项目
                
                if ($leftCapacity >= $ordMin) {
                    // 此时，如果剩余产能尚且能至少支持一托生产，则尽量在不超过产能的条件下生产更多的托数
                    $prodAmount = floor($leftCapacity / $ordMin) * $ordMin;
                    $this->partsInfo[$part]["prods"][$prodDate] += $prodAmount;
                    $this->availDayCapacities[$prodDate] -= $prodAmount;
                
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
    public function arrangeLeadDayProduction($part, $prodDate, $curNetDmd)
    {
        $ordMin = $this->partsInfo[$part]["ordMin"];
        $curNetDmdForPallets = ceil($curNetDmd / $ordMin) * $ordMin;
    
        $leftCapacity = $this->availDayCapacities[$prodDate];
    
        if ($leftCapacity >= $curNetDmdForPallets) {
            $this->partsInfo[$part]["prods"][$prodDate] += $curNetDmdForPallets;
            $this->availDayCapacities[$prodDate] -= $curNetDmdForPallets;
        } else if ($prodDate == $this->getStartActiveDate()) {
            $this->partsInfo[$part]["prods"][$prodDate] += $curNetDmdForPallets;
            $this->availDayCapacities[$prodDate] -= $curNetDmdForPallets;
        } else  {
    
            if ($leftCapacity >= $ordMin) {
                $prodAmount = floor($leftCapacity / $ordMin) * $ordMin;
                $this->partsInfo[$part]["prods"][$prodDate] += $prodAmount;
                $this->availDayCapacities[$prodDate] -= $prodAmount;
    
                $curNetDmd -= $prodAmount;
            }
    
            $preProdDate = $this->getClosestWorkdayDate($prodDate, false);
            $this->arrangeLeadDayProduction($part, $preProdDate, $curNetDmd);
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
                $curPart = $this->getLeastDayProdPartBetween($date, $partsForComplement);
                
                $this->partsInfo[$curPart]["prods"][$date] += $this->partsInfo[$curPart]["ordMin"];
                $this->availDayCapacities[$date] -= $this->partsInfo[$curPart]["ordMin"];
                
                // 零件当日被补充的总数需要记录下来，用于在可能发生的后个生产日超出产能时向前回溯判断要不要回溯生产的比较
                $this->partsInfo[$curPart]["complements"][$date] += $this->partsInfo[$curPart]["ordMin"];
                
            }
            
            while ($this->availDayCapacities[$date] >= $minOrdMin || ($this->allowOverCapacityInMinPallets && $this->availDayCapacities[$date] > 0)) {
                foreach ($partsForComplement as $part) {
                    if ($this->availDayCapacities[$date] >= $this->partsInfo[$part]["ordMin"]) {
                        $this->partsInfo[$part]["prods"][$date] += $this->partsInfo[$part]["ordMin"];
                        $this->availDayCapacities[$date] -= $this->partsInfo[$part]["ordMin"];
                    }
                }
            }
 
        }
    }
    
    
    /**
     * 根据初步最小生产量计算后的结果， 获取某日的产能补充作用的零件的优先级顺序。
     * 对某一日的所有 <需要重新mrp的>  <从该日期起还有后续日需求的> <非B515安特来源零件的>的零件号进行排序，排序规则依次：
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
            $isMrp = $partInfo["isMrp"];
            if ($isMrp && $this->hasDayDmdsFrom($part, $date)) {
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
            $prevDate = self::getDateBefore($date);
        } else {
            $prevDate = self::getPrevArrayElement($this->activeDates, $date);
        }
        

        
        $pInnerstock = $this->partsInfo[$part]["innerStocks"][$prevDate];
        $pOuterstock = $this->partsInfo[$part]["outerStocks"][$prevDate];
        $pOverallStock = $this->partsInfo[$part]["stocks"][$prevDate];
        
        $this->partsInfo[$part]["stocks"][$date] = $pOverallStock + $this->partsInfo[$part]["prods"][$date] - $this->partsInfo[$part]["dmds"][$date];
        
        if (!$this->isDayDate($date)) {
            return;
        }
        
        if ($this->_proj == '315a' || $this->partsInfo[$part]["isAnte"]) {
            $this->partsInfo[$part]["outerStocks"][$date] = 0;
            $this->partsInfo[$part]["innerStocks"][$date] = $this->partsInfo[$part]["stocks"][$date];
        } else {
            if (($this->isWorkday($date) && $this->isDmdDay($date)) || ($this->isMinProdRequiredDay($date))) {
                // 只在有客户需求的工作日，或是该日已产生最小生产需求，才考虑进行移库(此时确保让当天内库数只等于当天生产数即可）
                $this->partsInfo[$part]["innerStocks"][$date] = $this->partsInfo[$part]["prods"][$date];;
            } else {
                $this->partsInfo[$part]["innerStocks"][$date] = $this->partsInfo[$part]["innerStocks"][$prevDate];
                $this->partsInfo[$part]["outerStocks"][$date] = $this->partsInfo[$part]["stocks"][$date] - $this->partsInfo[$part]["innerStocks"][$date];
            }
        }
        
        $this->partsInfo[$part]["outerStocks"][$date] = $this->partsInfo[$part]["stocks"][$date] - $this->partsInfo[$part]["innerStocks"][$date];
        
        
        
        
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
            $date = self::getDateBefore($date);
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
    public function recalculateTotalAssyDayProds ()
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
        
        
        return $this->totalDayProds;
        
    }
    
    
    protected function getMaxBoxesProdAmountLimitOfDate ($date)
    {

        $prevStock = 0;
        $prevDate = self::getDateBefore($date);
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
    
    
    public function index ()
    {
        $this->DoAssemblyMrp();
        
        $this->assign("orgDate", $this->getOrgDate());
        $this->assign("dates", $this->getActiveDates());
        $this->assign("dateTypeMap", $this->dateTypeMap);
        $this->assign("isPeriodDateMap", $this->getAssyIsPeriodDateMap());
        $this->assign("isDoubleShiftDateMap", $this->getIsDoubleShiftDateMap());
        $this->assign("partsInfo", $this->partsInfo);
        $this->assign("isWorkdayDateMap", $this->getIsWorkdayDateMap());
        $this->assign("capacities", $this->getCapacities());
        $this->assign("totalDemands", $this->totalDayDmds);
        $this->assign("unusedCapacities", $this->availDayCapacities);
        $this->assign("totalProds", $this->totalDayProds);
        $this->assign("useSaftyStockRule", $this->useSaftyStockRule);
        

        
        $this->display();
    }
    
    public function updatePlans()
    {
        $rpsInfo = [];
        $ptpsInfo = [];
        
        foreach ($_REQUEST as $key => $val) { 
            list($r, $rpart, $rline, $rsite, $rdate, $rtype) = explode("#", $key);
            $rpart=str_replace('_', '.', $rpart);
            
            // 所有活动日期的计划日程数据，哪怕为0，都应该更新，从而可以覆盖之前可能已存在的计划数据值。
            $rpsInfo[] = [
                    "drps_part" => $rpart,
                    "drps_line" => $rline,
                    "drps_site" => $rsite,
                    "drps_date" => $rdate,
                    "drps_qty"  => floatval($val),
                    "drps_type" => $rtype
            ];
            
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
    
    static function getDateBefore($date, $interval = 1)
    {
        $interval = intval($interval);
        if ($interval < 0) {
            $interval = 0;
        }
        return date('Y-m-d', strtotime($date) - 86400 * $interval);
    }
    
    static function getDateAfter($date, $interval = 1)
    {
        $interval = intval($interval);
        if ($interval < 0) {
            $interval = 0;
        }
        return date('Y-m-d', strtotime($date) + 86400 * $interval);
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