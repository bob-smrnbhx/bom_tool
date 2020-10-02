<?php

namespace Home\Model;


class OrderAmountCalculatorModel 
{
    /**
     * @var array
     */
    protected $_demandDateMap = [];
    /**
     * @var array
     */
    protected $_transitDateMap = [];
    /**
     * provided or calculated data
     * @var array
     */
    protected $_orderDateMap = [];
    /**
     * flag to indicate whether to use the provided order data
     * @var boolean
     */
    protected $_useProvidedOrderData = false;
    /**
     * calculated data
     * @var array
     */
    protected $_stockDateMap = [];
    /**
     * @var array
     */
    protected $_shopDates = [];
    
    protected $_dateTypes = [];
    
    protected $_orderPer;
    
    protected $_orgDate = '0000-00-00';
    /**
     * @var integer
     */
    protected $_orgStock = 0;
    /**
     * @var integer
     */
    protected $_saftyStock = 0;
    /**
     * @var integer
     */
    protected $_baseOrderAmountForMultiple = 1;
    /**
     * @var integer
     */
    protected $_minOrderAmount = 1;
    
    protected $_curDemandPeriodStart, $_curDemandPeriodEnd;
    protected $_curTransitPeriodStart, $_curTransitPeriodEnd;
    /**
     * @var string
     */
    protected $_curOrderDate;
    

    
    
    public function __construct(array $options)
    {
        // org date is essential, either provided or default
        if (isset($options["orgDate"])) {
            self::enforceValidDateFormat($options["orgDate"]);
            $this->_orgDate = $options["orgDate"];
        }

        
        // demand date map is obligatory
        if (isset($options["demandDateMap"]) && $options["demandDateMap"]) {
            foreach ($options["demandDateMap"] as $date => $qty) {
                self::enforceValidDateFormat($date);
                if ($date >= $this->_orgDate) {
                    $this->_demandDateMap[$date] = floatval($qty);
                }
            }
            ksort($this->_demandDateMap);
        } else {
            throw new \Exception("demand date map is obligatory");
        }
        // allow empty transit date map
        if (isset($options["transitDateMap"]) && $options["transitDateMap"]) {
            foreach ($options["transitDateMap"] as $date => $qty) {
                self::enforceValidDateFormat($date);
                if ($date >= $this->_orgDate) {
                    $this->_transitDateMap[$date] = floatval($qty);
                }
            }
            ksort($this->_transitDateMap);
        }
        
        // order date map can be provided directly for stock amount calculation ,otherwise calculate both proposed order and stock amount.
        if (isset($options["orderDateMap"]) && $options["orderDateMap"]) {
            $this->_useProvidedOrderData = true;
            foreach ($options["orderDateMap"] as $date => $qty) {
                self::enforceValidDateFormat($date);
                if ($date >= $this->_orgDate) {
                    $this->_orderDateMap[$date] = floatval($qty);
                }
            }
            ksort($this->_orderDateMap);
        }
        
        
        
        // allow empty shop date 
        if (isset($options["shopDates"]) && $options["shopDates"]) {
            foreach ($options["shopDates"] as $date) {
                self::enforceValidDateFormat($date);
                if ($date >= $this->_orgDate) {
                    $this->_shopDates[] = $date;
                }
            }
            sort($this->_shopDates);
        }
        
        
        if (isset($options["dateTypes"]) && $options["dateTypes"]) {
            $this->_dateTypes = $options["dateTypes"];
        }
        
        if (isset($options["orderPer"]) && $options["orderPer"]) {
            $this->_orderPer = $options["orderPer"];
        }
        
        
        if (isset($options["orgStock"]) && $options["orgStock"] > 0) {
            $this->_orgStock = floatval($options["orgStock"]);
        }
        
        if (isset($options["saftyStock"]) && $options["saftyStock"] > 0) {
            $this->_saftyStock = floatval($options["saftyStock"]);
        }
        
        if (isset($options["baseOrderAmountForMultiple"]) && $options["baseOrderAmountForMultiple"] > 1) {
            $this->_baseOrderAmountForMultiple = floatval($options["baseOrderAmountForMultiple"]);
        }
        
        if (isset($options["minOrderAmount"]) && $options["minOrderAmount"] > 1) {
            $this->_minOrderAmount = floatval($options["minOrderAmount"]);
        }

    }
    
    /**
     * ensure date format like '2016-09-12'
     * @param string $date
     * @throws \Exception
     */
    public static function enforceValidDateFormat ($date) 
    {
        if (!preg_match('/\d{4}-\d{2}-\d{2}/', $date)) {
            throw new \Exception("invalid date format provided for: $date");
        }
    }
    
    
    public function getInitDemandDate()
    {
        return reset(array_keys($this->_demandDateMap));
    }
    
    public function getLastDemandDate()
    {
        return end(array_keys($this->_demandDateMap));
    }
    
    public function calculate()
    {
        
        if ($this->_useProvidedOrderData) {
            // if order date map is directly provided, just calculate the expected stock amount.
            $this->calculateStockData();
        } else {
            // otherwise, loop to calculate the order proposed and stock amounts.
            while ($this->forwardToNextShopPeriod() !== false) {
                $this->calculateCurrentShopPeriodData();
            }
        }

        return $this;
    }
    
    protected function forwardToNextShopPeriod()
    {
        $this->isDayEnd = false;
        
        
        if ($this->eof) {
            return false;
        }

        
        
        if (is_null($this->_curDemandPeriodEnd)) {
            $this->_curOrderDate = $this->getInitDemandDate();
        } else {
            $this->_curOrderDate = $this->_curDemandPeriodEnd;
        }
             

        if ($this->_curOrderDate == $this->getLastDemandDate()) {
            $this->eof = true;
            return true;
        }

        $this->_curDemandPeriodStart = self::getNextUpperValueInArray($this->_curOrderDate, array_keys($this->_demandDateMap), false);
        if ($this->_curDemandPeriodStart === false) {
            $this->_curDemandPeriodEnd = $this->_curOrderDate;
            return false;
        }
        if ($this->_dateTypes[$this->_curOrderDate] && !$this->_dateTypes[$this->_curDemandPeriodStart]) {
            $this->isDayEnd = true;
        }
        

        $nextShopDate = self::getNextUpperValueInArray($this->_curOrderDate, $this->_shopDates, false);
        if ($nextShopDate === false) {
            $this->_curDemandPeriodEnd = $this->getLastDemandDate(); 
        } else {
            $this->_curDemandPeriodEnd = self::getPrevLowerValueInArray($nextShopDate, array_keys($this->_demandDateMap), true);
            if ($this->_curDemandPeriodEnd < $this->_curDemandPeriodStart) {
                $this->_curOrderDate = false;
                return false;
            }
        }
        
        $this->_curTransitPeriodStart = $this->_curOrderDate;
        $this->_curTransitPeriodEnd = self::getPrevLowerValueInArray($this->_curDemandPeriodEnd, array_keys($this->_demandDateMap), false);
        if ($this->_curTransitPeriodEnd === false) {
            $this->_curTransitPeriodEnd = $this->_curTransitPeriodStart;
        }

        return true;
    }
    
    protected function addOrder ($date, $porder)
    {
        if ($date < max(array_keys($this->_orderDateMap))) {
            throw new \Exception("the added proposed order date must be larger than all existing date");
        }
        $this->_orderDateMap[$date] = $porder;
    }
    
    public function getOrder ($date)
    {
        if (isset($this->_orderDateMap[$date])) {
            return $this->_orderDateMap[$date];
        }
        return 0;
    }
    
    public function getOrderDateMap ()
    {
        foreach (array_keys($this->_demandDateMap) as $date) {
            if (!isset($this->_orderDateMap[$date])) {
                $this->_orderDateMap[$date] = 0;
            }
        }
        ksort($this->_orderDateMap);
        return $this->_orderDateMap;
    }
    
    protected function addExpectedStock ($date, $estock)
    {
        if (empty($this->_stockDateMap) || $date < max(array_keys($this->_stockDateMap))) {
            //throw new \Exception("the added expecting stock date must be larger than all existing date");
        }
        $this->_stockDateMap[$date] = $estock;
    }
    
    public function getLastStock()
    {
        if (empty($this->_stockDateMap)) {
            return $this->_orgStock;
        }
        return end($this->_stockDateMap);
    }
    
    /**
     * if curday_stock - nextday_demand is less than safty, treat curdate as invalid.s
     * and ignore first day constraint
     * @param string $invalidStockStartDate
     */
    public function getStockDateMap (&$invalidStockStartDate = null)
    {
        if (func_num_args() > 0) {
            $dates = array_keys($this->_stockDateMap);
            for ($i = 0; $i < count($dates); $i++) {
                $curDate = $dates[$i];
                if ($i == 0) {
                    $prevDate = 'org_date';
                    $prevStock = $this->_orgStock;
                    // ignore if invalid....
                } else {
                    $prevDate = $dates[$i - 1];
                    $prevStock = $this->_stockDateMap[$prevDate];
                    if ($prevStock - $this->_demandDateMap[$curDate] < $this->_saftyStock) {
                        $invalidStockStartDate = $prevDate;
                        break;
                    }
                }
            }
        }
        
        return $this->_stockDateMap;
    }
    
     
    
    protected function calculateCurrentShopPeriodData ()
    {
        if ($this->_curOrderDate  === false) {
            return false;
        }
        
        if ($this->isDayEnd) {
            $date = $this->_curOrderDate;
            $this->addOrder($date, 0);
            $preStock = $this->getLastStock();
            $stock = $preStock + $this->getOrder($date) + $this->_transitDateMap[$date] - $this->_demandDateMap[$date];
            $this->addExpectedStock($date, $stock);
            return;
        }
        
        if ($this->eof) {
            $date = $this->_curOrderDate;
            $this->addOrder($date, $this->_demandDateMap[$date]);
            $preStock = $this->getLastStock();
            $stock = $preStock + $this->getOrder($date) + $this->_transitDateMap[$date] - $this->_demandDateMap[$date];
            $this->addExpectedStock($date, $stock);
            return;
        }
        
        $preStock = $this->getLastStock();
        $periodDemandDateMap = self::getArrayIntervalBetweenKey($this->_demandDateMap, $this->_curOrderDate, $this->_curDemandPeriodEnd);
        $periodTransitDateMap = self::getArrayIntervalBetweenKey($this->_transitDateMap, $this->_curOrderDate, $this->_curTransitPeriodEnd);
// echo "<h2>for date: from: $this->_curOrderDate to: $this->_curDemandPeriodEnd</h2>";
        
//         $periodTotalDemand = array_sum($periodDemandDateMap); 
//         $periodTotalTransit = array_sum($periodTransitDateMap);
// // echo "<h1>period total demand: $periodTotalDemand</h1>";
// // echo "<h1>period total transit: $periodTotalTransit</h1>";
//         $leastShopAmount = $periodTotalDemand + $this->_saftyStock - $preStock - $periodTotalTransit;
//         if ($leastShopAmount < 0) {
//             $curShopAmount = 0;
//         } else {
//             $curShopAmount = ceil($leastShopAmount / $this->_baseOrderAmountForMultiple) * $this->_baseOrderAmountForMultiple;
//         }
//         $this->addOrder($this->_curOrderDate, $curShopAmount);
// // echo "<h2>calculated shop amount: $curShopAmount</h2>";
        

        if ($this->_dateTypes[$this->_curOrderDate] || $this->_orderPer > 7) {
            //对当前运输周期从起始日期至每一天，都需要分别计算一次采购量，获取每次计算的采购量最大值，才能保证每一天都能满足安全库存。
            //之前只计算运输周期从起始到最后的采购量的做法，是不恰当的。
            $periodDemandDates = array_keys($periodDemandDateMap);
            $subperiodShopAmounts = [];
            foreach ($periodDemandDates as $subDemandPeriodEnd) {
                $subperiodDemandDateMap = self::getArrayIntervalBetweenKey($this->_demandDateMap, $this->_curOrderDate, $subDemandPeriodEnd);
                $subperiodTotalDemand = array_sum($subperiodDemandDateMap);
                if ($subDemandPeriodEnd == $this->_curOrderDate) {
                    //$subperiodTotalTransit = 0;
                    // 应该在需求日至少为两日时再考虑进行计算。
                    continue;
                } else {
                    $subTransitPeriodEnd = self::getPrevLowerValueInArray($subDemandPeriodEnd, $periodDemandDates);
                    $subperiodTransitDateMap = self::getArrayIntervalBetweenKey($this->_transitDateMap, $this->_curOrderDate, $subTransitPeriodEnd);
                    $subperiodTotalTransit = array_sum($subperiodTransitDateMap);
                }
            
                $subleastShopAmount = $subperiodTotalDemand + $this->_saftyStock - $preStock - $subperiodTotalTransit;
                if ($subleastShopAmount <= 0) {
                    $subcurShopAmount = 0;
                } else {
                    $subcurShopAmount = ceil($subleastShopAmount / $this->_baseOrderAmountForMultiple) * $this->_baseOrderAmountForMultiple;
                    // 必须满足最小起订量的条件。同时，为防止ptp_ord_min数据维护不正确，不是ptp_ord_mult的整数倍，必须进行预先舍入处理。
                    if ($subcurShopAmount < $this->_minOrderAmount) {
                        $subcurShopAmount = ceil($this->_minOrderAmount / $this->_baseOrderAmountForMultiple) * $this->_baseOrderAmountForMultiple;
                    }
                }
                $subperiodShopAmounts[$subDemandPeriodEnd] = $subcurShopAmount;
            }
            $this->addOrder($this->_curOrderDate, max($subperiodShopAmounts));
        } else {
            $this->addOrder($this->_curOrderDate, $periodDemandDateMap[$this->_curOrderDate]);
        }
        




        // populate period stock
        $interval = self::getArrayIntervalBetweenKey($this->_demandDateMap, $this->_curOrderDate, $this->_curTransitPeriodEnd);
        // compensate for the last period stock population
        $endDemandDate = end(array_keys($this->_demandDateMap));
        if ($this->_curDemandPeriodEnd == $endDemandDate) {
            $interval += [$this->_curDemandPeriodEnd => $endDemandDate];
        }
        foreach ($interval as $date => $qty) {
            $preStock = $this->getLastStock();
            $stock = $preStock + $this->getOrder($date) + $this->_transitDateMap[$date] - $this->_demandDateMap[$date];
            $this->addExpectedStock($date, $stock);
        }
    }
    
    public function calculateStockData ()
    {
        foreach ($this->_demandDateMap as $date => $demQty) {
            $preStock = $this->getLastStock();
            $stock = $preStock + $this->getOrder($date) + $this->_transitDateMap[$date] - $this->_demandDateMap[$date];
            $this->addExpectedStock($date, $stock);
        }
    }
    
    

    
    public static function getArrayIntervalBetweenKey($arr, $fromKey, $toKey)
    {
        if ($fromKey > $toKey) {
            throw new \Exception("error interval key bound provided");
        }
        
        ksort($arr);
        $interval = [];
        foreach ($arr as $key => $val) {
            if ($key < $fromKey) {
                continue;
            }
            if ($key > $toKey) {
                break;
            }
            $interval[$key] = $val;
        }
        return $interval;
    }
    
    public static function getNextUpperValueInArray ($val, $arr, $allowEqual = false) 
    {
        if (empty($arr)) {
            return false;
        }
        sort($arr);
        foreach ($arr as $item) {
            if ($val < $item || ($allowEqual && $val == $item)) {
                return $item;
            }
        }
        return false;
    }
    
    public static function getPrevLowerValueInArray ($val, $arr, $allowEqual = false) 
    {
        if (empty($arr)) {
            return false;
        }
        rsort($arr);
        foreach ($arr as $item) {
            if ($val > $item || ($allowEqual && $val == $item)) {
                return $item;
            }
        }
        return false;
    }
    
    
}