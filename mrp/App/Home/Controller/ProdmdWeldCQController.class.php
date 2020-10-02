<?php

namespace Home\Controller;
use Think\Controller;
use Think\Model;

class ProdmdWeldCQController extends ProdmdAssyCQController 
{

    protected $adapter;
    
    protected function getProdAdapter()
    {
        if (empty($this->adapter)) {
            $this->adapter = new \Home\Model\WeldProdModel('6000');
        }
        
        return $this->adapter;
    }
    

    
    public function index ()
    {
        $adapter = $this->getProdAdapter();
        $adapter->doProcessMrp();
       
        
        $this->assign("dateThs", $adapter->getFormattedDatesMap());
        $this->assign("isWorkdayDateMap", $adapter->getIsWorkdayDateMap());
        $this->assign("orgDate", $adapter->getOrgDate());
        $this->assign("dates", $adapter->getActiveDates());
        $this->assign("dateTypeMap", $adapter->getDateTypeMap());
        $this->assign("partsInfo", $adapter->getPartsInfo());
        $this->assign("totalDmds", $adapter->getTotalDayDmds());
        $this->assign("capacities", $adapter->getCapacities());
        $this->assign("unusedCapacities", $adapter->getAvailCapacities());
        $this->assign("totalProds", $adapter->getTotalDayProds());
        $this->display();
    }
    
    
}