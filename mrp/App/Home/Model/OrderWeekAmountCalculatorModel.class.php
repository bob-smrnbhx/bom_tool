<?php

namespace Home\Model;


class OrderWeekAmountCalculatorModel 
{
    /**
     * @var array
     */
    protected $_demandWeekMap = [];
    /**
     * @var array
     */
    protected $_transitWeekMap = [];
    /**
     * provided or calculated data
     * @var array
     */
    protected $_orderWeekMap = [];
    /**
     * flag to indicate whether to use the provided order data
     * @var boolean
     */
    protected $_useProvidedOrderData = false;
    /**
     * calculated data
     * @var array
     */
    protected $_stockWeekMap = [];
    /**
     * @var array
     */
    protected $_shopWeek = [];

    
    protected $_orgWeek = '00年0月';
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
    
    protected $_curDemandPeriodStart, $_curDemandPeriodEnd;
    protected $_curTransitPeriodStart, $_curTransitPeriodEnd;
    /**
     * @var string
     */
    protected $_curOrderWeek;
    

    
    
    public function __construct(array $options)
    {
        // org week is essential, either provided or default
        if (isset($options["orgWeek"])) {
            self::enforceValidWeekFormat($options["orgWeek"]);
            $this->_orgWeek = $options["orgWeek"];
        }

        
 
        if (isset($options["demandWeekMap"]) && $options["demandWeekMap"]) {
            foreach ($options["demandWeekMap"] as $week => $qty) {
                self::enforceValidWeekFormat($week);
                if ($week >= $this->_orgWeek) {
                    $this->_demandWeekMap[$week] = floatval($qty);
                }
            }
            ksort($this->_demandWeekMap);
        } else {
            throw new \Exception("demand week map is obligatory");
        }
 
        if (isset($options["transitWeekMap"]) && $options["transitWeekMap"]) {
            foreach ($options["transitWeekMap"] as $week => $qty) {
                self::enforceValidWeekFormat($week);
                if ($week >= $this->_orgWeek) {
                    $this->_transitWeekMap[$week] = floatval($qty);
                }
            }
            ksort($this->_transitWeekMap);
        }
        
         
        if (isset($options["orderWeekMap"]) && $options["orderWeekMap"]) {
            $this->_useProvidedOrderData = true;
            foreach ($options["orderWeekMap"] as $week => $qty) {
                self::enforceValidWeekFormat($week);
                if ($week >= $this->_orgWeek) {
                    $this->_orderWeekMap[$week] = floatval($qty);
                }
            }
            ksort($this->_orderWeekMap);
        }
       
        if (isset($options["shopWeeks"]) && $options["shopWeeks"]) {
            foreach ($options["shopWeeks"] as $week) {
                self::enforceValidWeekFormat($week);
                if ($week >= $this->_orgWeek) {
                    $this->_shopWeek[] = $week;
                }
            }
            sort($this->_shopWeek);
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
   
    }
    
    /**
     * ensure week format like '11年15周'
     * @param string $week
     * @throws \Exception
     */
    public static function enforceValidWeekFormat ($week) 
    {
        if (!preg_match('/\d{2}年\d{1,2}周/', $week)) {
            throw new \Exception("invalid week format provided for: $week");
        }
    }
    
    
    public function getInitDemandWeek()
    {
        return reset(array_keys($this->_demandWeekMap));
    }
    
    public function getLastDemandWeek()
    {
        return end(array_keys($this->_demandWeekMap));
    }
    
    public function calculate()
    {
        if ($this->_useProvidedOrderData) {
            $this->calculateStockData();
        } else {
            while ($this->forwardToNextShopPeriod() !== false) {
                $this->calculateCurrentShopPeriodData();
            }
        }

        return $this;
    }
    
    protected function forwardToNextShopPeriod()
    {
        if ($this->_curOrderWeek === false) {
            return false;
        }
        if (is_null($this->_curDemandPeriodEnd)) {
            $this->_curOrderWeek = $this->getInitDemandWeek();
        } else {
            $this->_curOrderWeek = $this->_curDemandPeriodEnd;
        }
                       
        $this->_curDemandPeriodStart = self::getNextUpperValueInArray($this->_curOrderWeek, array_keys($this->_demandWeekMap), false);
        if ($this->_curDemandPeriodStart === false) {
            $this->_curDemandPeriodEnd = $this->_curOrderWeek = false;
            return false;
        }
        
        $nextShopWeek = self::getNextUpperValueInArray($this->_curOrderWeek, $this->_shopWeek, false);
        if ($nextShopWeek === false) {
            $this->_curDemandPeriodEnd = $this->getLastDemandWeek();
        } else {
            $this->_curDemandPeriodEnd = self::getPrevLowerValueInArray($nextShopWeek, array_keys($this->_demandWeekMap), true);
            if ($this->_curDemandPeriodEnd < $this->_curDemandPeriodStart) {
                $this->_curOrderWeek = false;
                return false;
            }
        }
        
        $this->_curTransitPeriodStart = $this->_curOrderWeek;
        $this->_curTransitPeriodEnd = self::getPrevLowerValueInArray($this->_curDemandPeriodEnd, array_keys($this->_demandWeekMap), false);
        if ($this->_curTransitPeriodEnd === false) {
            $this->_curTransitPeriodEnd = $this->_curTransitPeriodStart;
        }

        return true;
    }
    
    protected function addOrder ($week, $porder)
    {
        if ($week < max(array_keys($this->_orderWeekMap))) {
            throw new \Exception("the added proposed order week must be larger than all existing week");
        }
        $this->_orderWeekMap[$week] = $porder;
    }
    
    public function getOrder ($week)
    {
        if (isset($this->_orderWeekMap[$week])) {
            return $this->_orderWeekMap[$week];
        }
        return 0;
    }
    
    public function getOrderWeekMap ()
    {
        foreach (array_keys($this->_demandWeekMap) as $week) {
            if (!isset($this->_orderWeekMap[$week])) {
                $this->_orderWeekMap[$week] = 0;
            }
        }
        ksort($this->_orderWeekMap);
        return $this->_orderWeekMap;
    }
    
    protected function addExpectedStock ($week, $estock)
    {
        if (empty($this->_stockWeekMap) || $week < max(array_keys($this->_stockWeekMap))) {
            //throw new \Exception("the added expecting stock week must be larger than all existing week");
        }
        $this->_stockWeekMap[$week] = $estock;
    }
    
    public function getLastStock()
    {
        if (empty($this->_stockWeekMap)) {
            return $this->_orgStock;
        }
        return end($this->_stockWeekMap);
    }
    
    /**
     * if curweek_stock - nextweek_demand is less than safty, treat curweek as invalid.
     * and ignore first week constraint
     * @param string $invalidStockStartWeek
     */
    public function getStockWeekMap (&$invalidStockStartWeek = null)
    {
        if (func_num_args() > 0) {
            $weeks = array_keys($this->_stockWeekMap);
            for ($i = 0; $i < count($weeks); $i++) {
                $curWeek = $weeks[$i];
                if ($i == 0) {
                    $prevWeek = 'org_week';
                    $prevStock = $this->_orgStock;
                    // ignore if invalid....
                } else {
                    $prevWeek = $weeks[$i - 1];
                    $prevStock = $this->_stockWeekMap[$prevWeek];
                    if ($prevStock - $this->_demandWeekMap[$curWeek] < $this->_saftyStock) {
                        $invalidStockStartWeek = $prevWeek;
                        break;
                    }
                }
            }
        }
        
        return $this->_stockWeekMap;
    }
    
     
    
    protected function calculateCurrentShopPeriodData ()
    {
        if ($this->_curOrderWeek  === false) {
            return false;
        }

        $preStock = $this->getLastStock();
        $perioddemandWeekMap = self::getArrayIntervalBetweenKey($this->_demandWeekMap, $this->_curOrderWeek, $this->_curDemandPeriodEnd);
        $periodtransitWeekMap = self::getArrayIntervalBetweenKey($this->_transitWeekMap, $this->_curOrderWeek, $this->_curTransitPeriodEnd);


        $periodDemandWeeks = array_keys($perioddemandWeekMap);
        $subperiodShopAmounts = [];
        foreach ($periodDemandWeeks as $subDemandPeriodEnd) {
            $subperioddemandWeekMap = self::getArrayIntervalBetweenKey($this->_demandWeekMap, $this->_curOrderWeek, $subDemandPeriodEnd);
            $subperiodTotalDemand = array_sum($subperioddemandWeekMap);
            if ($subDemandPeriodEnd == $this->_curOrderWeek) {
                //$subperiodTotalTransit = 0;
                // 应该在需求周至少为两周时再考虑进行计算。
                continue;
            } else {
                $subTransitPeriodEnd = self::getPrevLowerValueInArray($subDemandPeriodEnd, $periodDemandWeeks);
                $subperiodtransitWeekMap = self::getArrayIntervalBetweenKey($this->_transitWeekMap, $this->_curOrderWeek, $subTransitPeriodEnd);
                $subperiodTotalTransit = array_sum($subperiodtransitWeekMap);
            }

            $subleastShopAmount = $subperiodTotalDemand + $this->_saftyStock - $preStock - $subperiodTotalTransit;
            if ($subleastShopAmount < 0) {
                $subcurShopAmount = 0;
            } else {
                $subcurShopAmount = ceil($subleastShopAmount / $this->_baseOrderAmountForMultiple) * $this->_baseOrderAmountForMultiple;
            }
 
            $subperiodShopAmounts[$subDemandPeriodEnd] = $subcurShopAmount;
        }
        $this->addOrder($this->_curOrderWeek, max($subperiodShopAmounts));



        // populate period stock
        $interval = self::getArrayIntervalBetweenKey($this->_demandWeekMap, $this->_curOrderWeek, $this->_curTransitPeriodEnd);
        // compensate for the last period stock population
        $endDemandWeek = end(array_keys($this->_demandWeekMap));
        if ($this->_curDemandPeriodEnd == $endDemandWeek) {
            $interval += [$this->_curDemandPeriodEnd => $endDemandWeek];
        }
        foreach ($interval as $week => $qty) {
            $preStock = $this->getLastStock();
            $stock = $preStock + $this->getOrder($week) + $this->_transitWeekMap[$week] - $this->_demandWeekMap[$week];
            $this->addExpectedStock($week, $stock);

        } 

    }
    
    public function calculateStockData ()
    {
        foreach ($this->_demandWeekMap as $week => $demQty) {
            $preStock = $this->getLastStock();
            $stock = $preStock + $this->getOrder($week) + $this->_transitWeekMap[$week] - $this->_demandWeekMap[$week];
            $this->addExpectedStock($week, $stock);
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