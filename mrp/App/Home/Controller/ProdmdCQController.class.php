<?php

namespace Home\Controller;
use Think\Controller;
use Think\Model;

class ProdmdCQController extends ProplanController
{
    private $_dmdModel;
    
    
    private $_proj;
    

    

    
    //protected $_maxConfig = 4; // or 2
    private $saftyDayLength = 4;

    
    protected $isWeekdayWorkableClassMaps = [
    ];
    protected $isWorkdayDateMap = [];
    
    protected $dateTypeMap = [];

    
    /**
     * 每日的总需求量数组，从初始日期开始（第一个活动日前一天）
     * @var array
     */
    protected $totalDayDmds = [];
    
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
            
            // the binary numbers indicating weekday work state for different product class, from Sunday to Saturday
            // use a default rules for all projects
            "classWeekdayWorkRules" => [
                    "" => ["" => 0b0111111]
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
                'c490'  => true,
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
                "315a"  => true
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
    
    /**
     *
     * the binary numbers indicating weekday work state for different product class, from Sunday to Saturday
     * @var array
     */
    protected $classWeekdayWorkRules = [
            "" => 0b01111111
    ];
    
    protected $specConds = [];
    protected $shiftCapacity;
    protected $useComplement = true;
    protected $useOuterStockForSafty = false;
    protected $prodRefLastExistingTotalDmd = false;
    protected $allowOverCapacityInMinPallets = false;
    
    protected $useBoxLimitation = false;
    protected $totalBoxAmount;
    protected $qtyPerBox;
    
    
    
    protected $baseProjConds = [];
    

    
    protected $assyPartsInfo = [];
    
    protected $activeAssyDates = [];
    protected $activeAssyDayDates = [];
    protected $activeAssyWeekDates = [];
    protected $activeAssyMonthDates = [];
    protected $daysLength = 14;
    protected $weeksLength = 6;
    protected $monthsLength;
    
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
        $this->today = '2017-05-09';
        

        if (isset($_REQUEST["proj"])) {
            $this->_proj = strtolower(trim($_REQUEST["proj"]));
        } else {
            $this->_proj = reset(array_keys($this->projAssyOptions["specConds"]));
        }
        
        // set properties according to specified project
        $this->specConds = $this->projAssyOptions["specConds"][$this->_proj];
        $this->baseProjConds = array_merge($this->baseCommonConds, $this->specConds);
        $this->shiftCapacity = $this->projAssyOptions["shiftCapacity"][$this->_proj];
        $this->useComplement =  $this->projAssyOptions["useComplement"][$this->_proj];
        $this->prodRefLastExistingTotalDmd = $this->projAssyOptions["prodRefLastWorkdayTotalDmd"][$this->_proj];
        $this->allowOverCapacityInMinPallets = $this->projAssyOptions["allowOverCapacityInMinPallets"][$this->_proj];
        if (isset($this->projAssyOptions["boxLimitationOptions"][$this->_proj])) {
            $this->useBoxLimitation = true;
            $this->totalBoxAmount = $this->projAssyOptions["boxLimitationOptions"][$this->_proj]["totalBoxAmount"];
            $this->qtyPerBox = $this->projAssyOptions["boxLimitationOptions"][$this->_proj]["qtyPerBox"];
        }
        $this->useOuterStockForSafty = $this->projAssyOptions["useOuterStockForSafty"][$this->_proj];
        
        $this->weekdayShiftsRules = isset($this->projAssyOptions["weekdayShiftRules"][$this->_proj]) ? $this->projAssyOptions["weekdayShiftRules"][$this->_proj] : $this->projAssyOptions["weekdayShiftRules"][''];
        
        $this->classes = $this->projAssyOptions["classes"][$this->_proj];
        $this->classWeekdayWorkRules = isset($this->projAssyOptions["classWeekdayWorkRules"][$this->_proj]) ? $this->projAssyOptions["classWeekdayWorkRules"][$this->_proj] : $this->projAssyOptions["classWeekdayWorkRules"][''];
        
        
        foreach ($this->getAllClasses() as $class) {
            $cwMap = self::convertBinToWeekdayMap($this->classWeekdayWorkRules[$class]);
            $cwMap[7] = $cwMap[0];
            unset($cwMap[0]);
            foreach ($cwMap as &$is) {
                $is = (bool)$is;
            }
            $this->isWeekdayWorkableClassMaps[$class] = $cwMap;
        }
        
        $activeDates = $this->getAssyActiveDates();

        // 初始化每日最大产能，从第一个活动日开始
        $this->availDayCapacities = $this->getAssyCapacities();

        
        // 获取所有装配零件信息， 该操作逻辑上必须放在计算并保存活动日期之后
        $partsInfo = $this->getAssyPartsInfo();
        
        // 初始化每日的总需求量,从初始日期开始，因为总需求量在某些项目中会被之后的总生产量考虑到
        $orgDate = $this->getOrgDate();
        foreach ($partsInfo as $part => $partInfo) {
            $this->totalDayDmds[$orgDate] += $partInfo["dmds"][$orgDate];
            foreach ($this->activeAssyDayDates as $date) {
                $this->totalDayDmds[$date] += $partInfo["dmds"][$date];
            }
        }
        
        
    }
    
    
    protected static function convertBinToWeekdayMap ($bin)
    {
        return str_split(str_pad(decbin($bin), 7, 0, STR_PAD_LEFT));
    }
    

    

    
//     /**
//      * 需要获取所有活动的需求日期，从最小到最大，中间任何一个可用日期都需要包含在内。
//      */
//     public function getAssyActiveDates ()
//     {
//         if (empty($this->_activeAssyDates)) {
//             $conds = $this->getBaseConds();
//             $conds['dmd_date'] = ['EGT', $this->getStartActiveDate()];
            
//             $result = $this->getDmdModel()
//             ->distinct(true)->field("dmd_date, dmd_type")
//             ->where($conds)->select();
//             $ddates = $wdates = $mdates = [];
//             foreach ($result as $item) {
//                 switch ($item["dmd_type"]) {
//                     case 'd':
//                         $ddates[] = $item["dmd_date"];
//                         break;
//                     case 'w':
//                         $wdates[] = $item["dmd_date"];
//                         break;
//                     case 'm':
//                         $mdates[] = $item["dmd_date"];
//                         break;
//                 }
//             }
            

            
//             // 日类型活动日范围强制从今天开始，到最大日类型需求日结束
//             $this->_activeAssyDayDates = self::getDatesBetween($this->today, max($ddates));
//             foreach ($this->_activeAssyDayDates as $date) {
//                 $this->_dateTypeMap[$date] =  'd';
//             }
//             $this->_daysLength =  count($this->_activeAssyDayDates);
            
//             if ($wdates) {
//                 $this->_activeAssyWeekDates = self::getMondaysBetween(min($wdates), max($wdates));
//                 foreach ($this->_activeAssyWeekDates as $date) {
//                     $this->_dateTypeMap[$date] =  'w';
//                 }
//                 $this->_weeksLength = count($this->_activeAssyWeekDates);
//             }

            
//             if ($mdates) {
//                 $this->_activeAssyMonthDates = self::getFirstMonthDayBetween(min($mdates), max($mdates));
//                 foreach ($this->_activeAssyMonthDates as $date) {
//                     $this->_dateTypeMap[$date] =  'm';
//                 }
//                 $this->_monthsLength = count($this->_activeAssyMonthDates);
//             }

            
//             // 将月日期分解为4个周日期
//             foreach ($this->_activeAssyMonthDates as $mdate)  {
//                 // to be continued.....
//             }
            
//             $this->_activeAssyDates = array_merge($this->_activeAssyDayDates, $this->_activeAssyWeekDates, $this->_activeAssyMonthDates);
            

//         }
        

        
//         return $this->_activeAssyDates;
//     }
    
    
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
    
    /**
     * 需要获取所有活动的需求日期，从最小到最大，中间任何一个可用日期都需要包含在内。
     * 按照预定义的规则来获取
     */
    public function getAssyActiveDates ()
    {
        if (empty($this->activeAssyDates)) {
            $conds = $this->getBaseConds();
            $conds['dmd_date'] = ['EGT', $this->getStartActiveDate()];
    
            $dates = $this->getDmdModel()
            ->distinct(true)->field("dmd_date")
            ->where($conds)->getField("dmd_date", true);
            $dates =  array_unique($dates);
            sort($dates);
            

            $orgDate = $this->getOrgDate();
            $this->dateTypeMap[$orgDate] =  'd';
            // 认定从昨天开始的固定14天（即从今天开始的13天）都是862需求
            $startDdate = $this->getStartActiveDate();
            $endDDate = date("Y-m-d", strtotime($startDdate) + 86400 * ($this->daysLength - 2));
            $this->activeAssyDayDates = self::getDatesBetween($startDdate, $endDDate);
            sort($this->activeAssyDayDates);
            foreach ($this->activeAssyDayDates as $date) {
                $this->dateTypeMap[$date] =  'd';
            }

            
            // 认定之后的日期到最大日期都是830需求,最多只取固定的6个即可。
            foreach ($dates as $date) {
                if ($date > $endDDate) {
                    $this->activeAssyWeekDates[] = $date;
                    
                    if (count($this->activeAssyWeekDates) >= $this->weeksLength) {
                        break;
                    }
                }
            }
            sort($this->activeAssyWeekDates);
            foreach ($this->activeAssyWeekDates as $date) {
                $this->dateTypeMap[$date] =  'w';
            }
            
            
 
            $this->activeAssyDates = array_merge($this->activeAssyDayDates, $this->activeAssyWeekDates, $this->activeAssyMonthDates);
 
        }
    

        return $this->activeAssyDates;
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
    
    /**
     * 获取所有日期是否是830日期的判断映射
     * @return boolean[]
     */
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
    
    
    /**
     * 判断日期是否是通用维护的工作日，不分配置类型进行通用判断
     * @param string $date
     * @return boolean
     */
    public function isCommonWorkday ($date)
    {
        $wd = date("N", strtotime($date));
        return $this->weekdayShiftsRules[$wd] != 0;
    }
    
    
    /**
     * 判断指定日期是否可以进行生产，只有当天有客户总需求存在，并且是通用工作日，才认为是可生产日
     * 否则，即使是工作日，但当天没有任何客户总需求，则不进行生产
     * @param unknown $date
     */
    protected function isProductableDay ($date)
    {
        if ($this->totalDayDmds[$date] && $this->isCommonWorkday($date)) {
            return true;
        }
        
        return false;
    }
    
    
    /**
     * 只考虑860日期产能，830日期产能不考虑
     * @return number[]
     */
    protected function getAssyCapacityDateMap ()
    {
        $dates = $this->getAssyActiveDates();
        $map = [];
        foreach ($dates as $date) {
            if ($this->isDayDate($date)) {
                $wd = date("N", strtotime($date));
                $map[$date] = $this->weekdayShiftsRules[$wd] * $this->shiftCapacity;
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
        
            if ($this->weekdayShiftsRules[$wd] == 2) {
                $map[$date] = true;
            } else {
                $map[$date] = false;
            }
        
        }
 
        
        return $map;
    }
    
    
    
    public function getAssyCapacityByDate ($date)
    {
        $wd = date("N", strtotime($date));
        return $this->weekdayShiftsRules[$wd] * $this->shiftCapacity;
    }
    
    protected function getAssyCapacities ()
    {
        if (empty($this->dayCapacities)) {
            foreach ($this->activeAssyDayDates as $date) {
                $wd = date("N", strtotime($date));
                $this->dayCapacities[$date] = $this->weekdayShiftsRules[$wd] * $this->shiftCapacity;
            }
        }
        
        return $this->dayCapacities;
    }
    
    protected function isWorkdayByClassAndDate ($class, $date)
    {
        if (!$this->isDayDate($date)) {
            // 非日类型日期始终允许为工作日
            return true;
        }
        
        $cwMap = $this->isWeekdayWorkableClassMaps[$class];
        $wd = date("N", strtotime($date));
        return $cwMap[$wd];
    }
    
    protected function getAssyClassIsWorkdayDateMap ()
    {
        $dates = $this->getAssyActiveDates();
        $map = [];
        foreach ($this->getAllClasses() as $class) {
            $map[$class] = [];
            foreach ($dates as $date) {
                $map[$class][$date] = $this->isWorkdayByClassAndDate($class, $date);
            }
        }
    
    
        return $map;
    }
    
    
//     protected function getAvailBoxAmountLeft ($date)
//     {
//         $activeDates = $this->getAssyActiveDates();
//         $startActiveDate = reset($activeDates);
        
//         if ($date == $startActiveDate) {
            
//         } else {
            
//         }
//     }

    
    /**
     * 只获取在最近的库存日期之后的客户需求
     * 
     */
    protected function _search ()
    {
        $conds = $this->getBaseConds();
//         $conds["dmd_date"] = [
//                 ['EGT', self::getDateBefore($this->getStartActiveDate())],
//                 ['EXP', 'is null'],
//                 'OR'
//         ];
        $conds["dmd_date"] = ['EGT', $this->orgDate];
        //$conds['dmd_type'] = 'd';

        
        return $conds;
    }
    
    public function getAssyPartsInfo()
    {
        if (empty($this->assyPartsInfo)) {
            $conds = $this->_search();
            $result = $this->getDmdModel()->where($conds)->order("ptp_desc1")->select();
            
            $activeDates = $this->getAssyActiveDates();
            $orgDate = $this->getOrgDate();

            foreach ($result as $row) { 
                $part = $row["ptp_part"];
                if (!isset($this->assyPartsInfo[$part])) {
                    $class = '';
                    $this->assyPartsInfo[$part] = [
                            'isMrp'    => (bool)$row["ptp_isdmrp"],
                            'part'     => $row["ptp_part"],
                            'site'     => $row["ptp_site"],
                            'desc1'    => $row["ptp_desc1"],
                            'desc2'    => $row["ptp_desc2"],
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
                }
                
                // 如果制造件不需要重新进行mrp，直接读取对应的生产计划数据即可
                if (!$this->assyPartsInfo[$part]["isMrp"]) {
                    // 已有某日期的计划数据时才进行记录
                    if ($row['drps_date'] != null && !empty($row['drps_qty']) && !isset($this->assyPartsInfo[$part]['prods'][$row['drps_date']])) {
                        $this->assyPartsInfo[$part]['prods'][$row['drps_date']] =  floatval($row['drps_qty']);
                    }
                }
                
                if (!isset($this->assyPartsInfo[$part]["innerStocks"][$orgDate]) && $row["in_type"] == 'i') {
                    $this->assyPartsInfo[$part]["innerStocks"][$orgDate] = floatval($row["in_qty_oh"]);
                }
                
                if (!isset($this->assyPartsInfo[$part]["outerStocks"][$orgDate]) && $row["in_type"] == 'o') {
                    $this->assyPartsInfo[$part]["outerStocks"][$orgDate] = floatval($row["in_qty_oh"]);
                }
                
                
                
                if (!isset($this->assyPartsInfo[$part]['dmds'][$row["dmd_date"]][$row["sod_ship"]])) {
                    $this->assyPartsInfo[$part]['dmds'][$row["dmd_date"]][$row["sod_ship"]] = floatval($row['dmd_qty']);
                    $this->assyPartsInfo[$part]['dmdTypes'][$row["dmd_date"]] = floatval($row["dmd_type"]);
                }
            }

            foreach ($this->assyPartsInfo as $part => $partInfo) {
                // 叠加所有订单的862需求量和初始需求量
                foreach ($this->assyPartsInfo[$part]['dmds'] as $date => $dmds) {
                    $this->assyPartsInfo[$part]['dmds'][$date] = array_sum($dmds);
                }
                
                // 计算初始日期的实际外库库存和实际总库存量
                $this->assyPartsInfo[$part]["outerStocks"][$orgDate] = $this->assyPartsInfo[$part]["outerStocks"][$orgDate] - $this->assyPartsInfo[$part]["dmds"][$orgDate];
                $this->assyPartsInfo[$part]['stocks'][$orgDate] = $this->assyPartsInfo[$part]["innerStocks"][$orgDate] + $this->assyPartsInfo[$part]["outerStocks"][$orgDate];
                
                // 每个零件的日需求量映射，按日期从小到大排序。
                ksort($this->assyPartsInfo[$part]['dmds']);
            
                // 累计每个零件的活动日的862需求总需求量作为参考
                $this->assyPartsInfo[$part]["dmdsSum"] = 0;
                foreach ($this->assyPartsInfo[$part]['dmds'] as $date => $qty) {
                    if ($this->isDayDate($date) && $date != $orgDate) {
                        $this->assyPartsInfo[$part]["dmdsSum"] += $qty;
                    }
                }
            
                // 活动日862总需求量为0的零件，不参与生产计划安排
                $this->assyPartsInfo[$part]["hasDayDmds"] = !empty($this->assyPartsInfo[$part]["dmdsSum"]);
                
                foreach ($activeDates as $date) {
                    // 获取每个零件每日的从此以后的累计覆盖日需求量
                    $this->getAccuDayDmdFrom($part, $date);
                }
            }

        }
        
        return $this->assyPartsInfo;
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
            foreach ($this->assyPartsInfo as $part => $partInfo) {
                if ($this->assyPartsInfo[$part]['class'] == $class) {
                    $filteredPartsInfo[$part] = $this->assyPartsInfo[$part]['class'];
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
        
        
        
        $activeDates = $this->getAssyActiveDates();
        $orgDate = $this->getOrgDate();
        $classNetAccuDmds = [];
        foreach ($this->assyPartsInfo as $part => $partInfo) {
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
    

    
    protected function hasDayDmdsFrom($part, $date)
    {
        foreach ($this->assyPartsInfo[$part]['dmds'] as $curDate => $qty) {
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
    
    
    public function getMrpedParts()
    {
        $parts = [];
        foreach ($this->assyPartsInfo as $partInfo) {
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
        foreach ($this->assyPartsInfo as $part => $partInfo) {
            if ($partInfo["isMrp"]) {
                $parts[] = $part;
            }
        }
        
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
        $activeDates = self::getAssyActiveDates();
        $orgDate = $this->getOrgDate();
        foreach ($this->assyPartsInfo as $part => $partInfo) {
            if (!$partInfo["isMrp"]) {
                continue;
            }

            $prevDate = self::getDateBefore($curDate);
            $prevStock = $this->assyPartsInfo[$part]["stocks"][$prevDate];
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
        
        if (!isset($this->assyPartsInfo[$part]["consectAccuDmds"][$fromDate])) {
            // 根据安全库存日期长度计算连续累计库存需求量
            $consectiveDmds = self::getSubArrayFromKey($this->assyPartsInfo[$part]["dmds"], $fromDate, $len, true);
            $consectiveAccuDayDmd = 0;
 
            // 只考虑862需求类型的需求量和第一个830周分解后的需求
            foreach ($consectiveDmds as $date => $dmdQty) {
                if ($this->isDayDate($date)) {
                    $consectiveAccuDayDmd += $dmdQty;
                } else if ($len >= 1 && $date == $this->activeAssyWeekDates[0]) {
                    // 将开始的830日期进行分解成6天日需求,将前几日当做日需求来补足连续需求量
                    $consectiveAccuDayDmd += floor($dmdQty / 6) * $len;
                    break;
                }
                $len--;
            }
            $this->assyPartsInfo[$part]["consectAccuDmds"][$fromDate] = $consectiveAccuDayDmd;
        }
        
        return  $this->assyPartsInfo[$part]["consectAccuDmds"][$fromDate];
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
    
    public function test ()
    {
        $this->getAssyPartsInfo();
        
        foreach ($this->getAssyActiveDates() as $date) {
//             $lastDmdDate = $this->getLastExistingDmdDate($date);
//             var_dump($date, $lastDmdDate, $this->totalDayDmds[$lastDmdDate]);
            var_dump($date, $this->isProductableDay($date));
            echo "<hr />";
        }
    }
    
//     protected function getDayDmdForMfgLead ($part, $date)
//     {
        
//     }
    
    /**
     * 根据初步最小生产量计算后的结果，获取某日的产能补充作用的零件的优先级顺序。
     * 对某一日的所有 <需要重新mrp的> <可生产的> 并且 <从该日期起还有后续日需求的> 零件号进行排序，排序规则依次：
     * 已安排生产的，统一在未安排生产的之前；
     * 库存量小的，在库存量大的之前
     * @param unknown $date
     */
    public function getPartsComplementPriorityByDate ($date)
    {
        $partStockMap = [];
        $produedPartStockMap = $notProducedPartStockMap = [];
        foreach ($this->assyPartsInfo as $part => $partInfo) {
            $isMrp = $partInfo["isMrp"];
            $class = $partInfo["class"];
            if ($isMrp && $this->isProductableDay($date) && $this->hasDayDmdsFrom($part, $date)) {
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
        if (empty($this->assyPartsInfo)) {
            return;
        }
        
 
        $mrpedParts = $this->getMrpedParts();
        $unmrpedparts = $this->getUnmrpedParts();

        // 只对862的需求进行MRP运算
        foreach ($this->activeAssyDayDates as $curDate) {
            // 先对已进行mrp的零件进行产能扣减
            foreach ($mrpedParts as $part) {
                $this->availDayCapacities[$curDate] -= $this->assyPartsInfo[$part]["prods"][$curDate];
            }
            
            $curIndex = array_keys($this->activeAssyDayDates, $curDate)[0];
            $pastDates = array_slice($this->activeAssyDayDates, 0, $curIndex + 1);
            $prevDate = self::getDateBefore($curDate);
            
            if ($this->prodRefLastExistingTotalDmd) {
                $lastExistingDmdDate = $this->getLastExistingDmdDate($curDate);
                foreach ($unmrpedparts as $part) {
                    // 只在可生产日进行排产
                    if ($this->isProductableDay($curDate)) {
                        // 当日排产数参考上一个需求存在日的需求数的整托数（
                        // ※※※暂时设定为统一不超过前需求数，否则(使用ceil())，将会发现，很有可能严重超出产能!!!
                        // 实际上，应设定一个均衡算法，部分可超出，部分不超出，从而让总生产量接近前需求存在日总需求量，但复杂度极高……
                        $ordMin = $this->assyPartsInfo[$part]["ordMin"];
                        $palletsAmount = floor($this->assyPartsInfo[$part]["dmds"][$lastExistingDmdDate] / $ordMin);
                        
                        // ※※※当上一个需求存在日有需求，则至少本日排产1箱（该规则是否合适存疑!!!）
                        // 经验证，该规则可能导致部分每日小批量需求而每拖数偏大的物料排产后库存爆炸!!!
//                         if ($this->assyPartsInfo[$part]["dmds"] && $palletsAmount == 0) {
//                             $palletsAmount = 1;
//                         }
                        
                        $lastDmdDateAmountOfPallets = $palletsAmount * $ordMin;
                        
                        $this->assyPartsInfo[$part]["prods"][$curDate] = $lastDmdDateAmountOfPallets;
                        $this->availDayCapacities[$curDate] -= $lastDmdDateAmountOfPallets;
                    }

                    $this->calculatePartStocksBetween($part, [$curDate]);
                }
            } 
 
 
 
            // 之后再进行其他额外MRP运算
            
            // 按照当前需求日起的覆盖库存需求数，来确定各配置零件的排产的优先级
            $unmrpedparts = $this->getUnmrpedPartsByPredictedProdPriority($curDate, $this->saftyDayLength);
            foreach ($unmrpedparts as $part) {
                $class = $this->assyPartsInfo[$part]["class"];
            
                $prevDate = self::getDateBefore($curDate);
                $prevOverallStock = $this->assyPartsInfo[$part]["stocks"][$prevDate];
                $curDmd = $this->assyPartsInfo[$part]["dmds"][$curDate];
                $curProd = $this->assyPartsInfo[$part]["prods"][$curDate];   // 此时当前日期生产量，在之前未进行过其他规则排产（如参考上一需求日排产）时，将总是为0.
            
                // 根据安全库存日期长度计算累计库存需求量
                $consectiveDmdsAccu = $this->getAccuDayDmdFrom($part, $curDate);
            
                // 当前日期是可生产日，且该零件有总需求(此时才有必要排计划)，才考虑“补充”进行最小生产性生产，来保证库存满足N天连续需求日覆盖
                if ($this->isProductableDay($curDate) && $this->assyPartsInfo[$part]["hasDayDmds"]) {
                    $netDmd = $consectiveDmdsAccu + $curDmd - $curProd - $prevOverallStock;
            
                    // 只要某日的累计净需求量多于上一次的库存结余，就必须安排生产
                    if ($netDmd > 0) {
                        //$prodDate = $this->getClassClosestWorkdayDate($this->_assyPartsInfo[part]["class"], $curDate, true); // 如果是非生产日也要确保N天连续需求覆盖，生产只能安排在最接近或等同的'可生产日'（该值很可能与本次需求日日期不同）
            
                        // 本次需求覆盖所安排的生产量必须累加（而不是直接设置）到'可生产日'的原生产量上去
                        // 某零件当日的第一次额外最小量排产，设置回溯标志为false
                        $this->arrangeMinExtraAssyProduction($part, $curDate, $netDmd, false);
                    }
                }
            
            
            
                // 由于生产日可能一次或多次向前安排，每个零件每个活动天进行计划安排后都必须重新计算之前每天包括当天的库存结余
                $curIndex = array_keys($this->activeAssyDayDates, $curDate)[0];
                $pastDates = array_slice($this->activeAssyDayDates, 0, $curIndex + 1);
                $this->calculatePartStocksBetween($part, $pastDates);
            
            }
            
            //$this->getMaxBoxesProdAmountLimitOfDate($curDate);
            
        }
        

        
        
        // 只有非共线的，需要进行产能补充的项目，再进行产能补充
        if ($this->useComplement) {
            // 在安排完每日最小计划生产量后，再逐日进行产能补充（在某日有剩余产能的时候）。
            $this->complementDaysCapacity();
    
            // 逐日补充产能后，重新计算所有活动日的库存结余（全部活动日重新计算也可以）
            foreach ($this->assyPartsInfo as $part => $partInfo) {
                $this->calculatePartStocksBetween($part, $this->activeAssyDayDates);
            }
        }
        


    }
    
 

    /**
     * @param unknown $part
     * @param unknown $prodDate
     * @param unknown $curNetDmd
     * @param bool $isBackdate
     */
    protected function arrangeMinExtraAssyProduction($part, $prodDate, $curNetDmd, $isBackdate)
    {
        $ordMin = $this->assyPartsInfo[$part]["ordMin"];
        $curNetDmdForPallets = ceil($curNetDmd / $ordMin) * $ordMin; 
        
        $leftCapacity = $this->availDayCapacities[$prodDate];
        
        if ($leftCapacity >= $curNetDmdForPallets) {
            // 如果'生产日'剩余产能大于等于该零件的当前净整托需求，可以安排满足全额净托需求的生产
            $this->assyPartsInfo[$part]["prods"][$prodDate] += $curNetDmdForPallets;
            $this->availDayCapacities[$prodDate] -= $curNetDmdForPallets;
        } else if ($prodDate == $this->getStartActiveDate()) {
            //  否则，如果生产日是第一活动日，没办法向前递推，只能安排在该天超额生产
            $this->assyPartsInfo[$part]["prods"][$prodDate] += $curNetDmdForPallets;
            $this->availDayCapacities[$prodDate] -= $curNetDmdForPallets;
        } else  {
            // 否则如果还有产能
            
            if ($this->allowOverCapacityInMinPallets) {
                // 对于可以以刚刚好的整托数超过产能的项目，尽量在刚刚超过产能的条件下生产更多的托数
                
                $prodAmount = ceil($leftCapacity / $ordMin) * $ordMin;
                $this->assyPartsInfo[$part]["prods"][$prodDate] += $prodAmount;
                $this->availDayCapacities[$prodDate] -= $prodAmount;
                
                // 只是仍然可能残存一定的净需求
                $curNetDmd -= $prodAmount;
            } else {
                // 对于总是不可以超过产能的项目
                
                if ($leftCapacity >= $ordMin) {
                    $prodAmount = floor($leftCapacity / $ordMin) * $ordMin;
                    // 此时，如果剩余产能尚且能至少支持一托生产，则尽量在不超过产能的条件下生产更多的托数
                    $this->assyPartsInfo[$part]["prods"][$prodDate] += $prodAmount;
                    $this->availDayCapacities[$prodDate] -= $prodAmount;
                
                    // 只是仍然可能残存一定的净需求
                    $curNetDmd -= $prodAmount;
                } else {
                    // 否则，连一托数的剩余产能都不足，则本日不进行生产了
                }
            }

            // 然后，还需将可能残存的需求部分，移到前一个"可生产日"进行生产(该步骤可递归进行)
            $preProdDate = $this->getClassClosestWorkdayDate($this->assyPartsInfo[$part]["class"], $prodDate, false);
            //var_dump($prodDate, $preProdDate);
            // 当某零件进行当日的额外最小量回溯排产，设置回溯标志为true,这部分回溯的需求量，是强制性的。
            $this->arrangeMinExtraAssyProduction($part, $preProdDate, $curNetDmd, true);
            

        }
    }
    
    /**
     * 在安排初步最少量生产安排后，进行每日产能补充，并重新计算库存
     */
    protected function complementDaysCapacity ()
    {
        foreach ($this->activeAssyDayDates as $date) {
            // 根据涉每日生产零件配置类型，将每日产能排满
            if ($this->availDayCapacities[$date] >= 0) {
                // 按照指定的规则，提取出待补充产能的优先级最高的最多10个零件，进行产能补充(此条规则适用性存疑!!!)
                $partsForComplement = array_slice($this->getPartsComplementPriorityByDate($date), 0, 10);
    
                if (!empty($partsForComplement)) {
                    $minOrdMin = null;
                    foreach ($partsForComplement as $part) {
                        if (is_null($minOrdMin)) {
                            $minOrdMin = $this->assyPartsInfo[$part]["ordMin"];
                        } else {
                            if ($this->assyPartsInfo[$part]["ordMin"] < $minOrdMin) {
                                $minOrdMin = $this->assyPartsInfo[$part]["ordMin"];
                            }
                        }
                    }
    
                    while ($this->availDayCapacities[$date] >= $minOrdMin) {
                        foreach ($partsForComplement as $part) {
                            if ($this->availDayCapacities[$date] >= $this->assyPartsInfo[$part]["ordMin"]) {
                                $this->assyPartsInfo[$part]["prods"][$date] += $this->assyPartsInfo[$part]["ordMin"];
                                $this->availDayCapacities[$date] -= $this->assyPartsInfo[$part]["ordMin"];
                            }
                        }
                    }
                    

                    
                    // 如果允许以最小托数超出超能，且剩余超能还稍有富余（此时一定小于$minOrdMin），还要进行补充
                    if ($this->allowOverCapacityInMinPallets && $this->availDayCapacities[$date] > 0) {
                        
                        foreach ($partsForComplement as $part) {
                            if ($this->assyPartsInfo[$part]["ordMin"] == $minOrdMin) {
                                $this->assyPartsInfo[$part]["prods"][$date] += $this->assyPartsInfo[$part]["ordMin"];
                                $this->availDayCapacities[$date] -= $this->assyPartsInfo[$part]["ordMin"];
                                break;
                            }
                        }
                    }
                }
    
            }
        }
    }
    
    
    protected function calculatePartStocksBetween ($part, array $dates)
    {
        sort($dates);
        $startDate = reset($dates);
    
    
        foreach ($dates as $date)  {
            $prevDate = self::getDateBefore($date);
            $pInnerstock = $this->assyPartsInfo[$part]["innerStocks"][$prevDate];
            $pOuterstock = $this->assyPartsInfo[$part]["outerStocks"][$prevDate];
            $pOverallStock = $this->assyPartsInfo[$part]["stocks"][$prevDate];
    
            $this->assyPartsInfo[$part]["stocks"][$date] = $pOverallStock + $this->assyPartsInfo[$part]["prods"][$date] - $this->assyPartsInfo[$part]["dmds"][$date];
    
            if ($this->assyPartsInfo[$part]["dmds"][$date] || $this->assyPartsInfo[$part]["prods"][$date] || $date == $startActiveDate) {
                // 内库最小允许数量为当日生产数，外库最大允许数量为当日最终结余-当日生产数，按照该原则进行内外库库存转移，
                // 使外库库存尽量接近或等于（但不能超过）当日起（不含当日）的累计安全库存天数需求数
    
    
                if ($this->assyPartsInfo[$part]["consectAccuDmds"][$date] < $this->assyPartsInfo[$part]["stocks"][$date] - $this->assyPartsInfo[$part]["prods"][$date]) {
                    $this->assyPartsInfo[$part]["outerStocks"][$date] = $this->assyPartsInfo[$part]["consectAccuDmds"][$date];
                } else {
                    $this->assyPartsInfo[$part]["outerStocks"][$date] = $this->assyPartsInfo[$part]["stocks"][$date] - $this->assyPartsInfo[$part]["prods"][$date];
                }
    
                // 当日外库库存+当日需求（即外库未扣减前），不允许少于前日外库库存结余
                if ($this->assyPartsInfo[$part]["outerStocks"][$date] + $this->assyPartsInfo[$part]["dmds"][$date] < $pOuterstock) {
                    $this->assyPartsInfo[$part]["outerStocks"][$date] = $pOuterstock - $this->assyPartsInfo[$part]["dmds"][$date];
                    if ($this->assyPartsInfo[$part]["outerStocks"][$date] < 0) {
                        $this->assyPartsInfo[$part]["outerStocks"][$date] = 0;
                    }
                }
    
                $this->assyPartsInfo[$part]["innerStocks"][$date] = $this->assyPartsInfo[$part]["stocks"][$date] - $this->assyPartsInfo[$part]["outerStocks"][$date];
    
    
            }
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
    
    
    
    
    public function getClassClosestWorkdayDate($class, $date, $allowCurrent = true)
    {
        $wdMap = $this->isWeekdayWorkableClassMaps[$class];
        $curWd = date("N", strtotime($date));
        
        if ($wdMap[$curWd] && $allowCurrent) {
            return $date;
        }
        
        if ($date == $this->today) {
            return $date;
        }
        
        $D = new \DateTime($date);
        do {
            $D->sub(new \DateInterval('P1D'));
            $d = $D->format("Y-m-d");
            // 如果是第一活动天，强制作为最近工作日
            if ($d == $this->today) {
                break;
            }
        } while (!self::isWorkdayByClassAndDate($class, $d));
        
        return $d;
    }
    
    
    public function getTotalAssyDayDmds ()
    {
        return $this->totalDayDmds;
    }
 
    public function getAvailAssyDayCapacities ()
    {
        return $this->availDayCapacities;
    }
    
    
    public function getUsedAssyDayCapacities ()
    {
        $usedCapacities = [];
        $overallCapacities = $this->getAssyCapacityDateMap();
        
        foreach ($this->getAvailAssyDayCapacities() as $part => $availCapacity) {
            $usedCapacities[$part] = $overallCapacities[$part] - $availCapacity;
        }
        
        return $usedCapacities;
    }
    
    
    protected function getMaxBoxesProdAmountLimitOfDate ($date)
    {

        $prevStock = 0;
        $prevDate = self::getDateBefore($date);
        foreach ($this->getAssyPartsInfo() as $part => $info) {
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
        $this->assign("dates", $this->getAssyActiveDates());
        $this->assign("isPeriodDateMap", $this->getAssyIsPeriodDateMap());
        $this->assign("isDoubleShiftDateMap", $this->getAssyIsDoubleShiftDateMap());
        $this->assign("partsInfo", $this->assyPartsInfo);
        $this->assign("isClassWorkdayDateMap", $this->getAssyClassIsWorkdayDateMap());
        $this->assign("totalDemands", $this->getTotalAssyDayDmds());
        $this->assign("capacities", $this->getAssyCapacities());
        $this->assign("unusedCapacities", $this->getAvailAssyDayCapacities());
        $this->assign("usedCapacities", $this->getUsedAssyDayCapacities());
        
        
        $this->display();
    }
    
    public function updatePlans()
    {
        $rpsInfo = [];
        $ptpsInfo = [];
        
        foreach ($_REQUEST as $key => $val) { 
            list($rtype, $rpart, $rline, $rsite, $rdate) = explode("#", $key);
            $rpart=str_replace('_', '.', $rpart);
            
            // 所有活动日期的计划日程数据，哪怕为0，都应该更新，从而可以覆盖之前可能已存在的计划数据值。
            $rpsInfo[] = [
                    "drps_part" => $rpart,
                    "drps_line" => $rline,
                    "drps_site" => $rsite,
                    "drps_date" => $rdate,
                    "drps_qty"  => floatval($val),
                    "drps_type" => $this->dateTypeMap[$rdate]
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
    
    static function getDateBefore($date, $interval = 1)
    {
        $interval = intval($interval);
        if ($interval < 1) {
            $interval = 1;
        }
        return date('Y-m-d', strtotime($date) - 86400 * $interval);
    }
    
    static function getDateAfter($date, $interval = 1)
    {
        $interval = intval($interval);
        if ($interval < 1) {
            $interval = 1;
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
}