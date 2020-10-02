<?php

namespace Home\Controller;
use Think\Controller;
use Think\Model;
import("Date.DateHelper");

class ProdmdPaintCQController extends ProdmdWeldCQController 
{
    protected $projAdapter;
    
    protected $projDirDailyCaps = [];

    protected $subProj;
    
    protected function getProdAdapter()
    {
        if (empty($this->adapter)) {
            $this->adapter = new \Home\Model\PaintProdModel(6000, $this->subProj);
        }
    
        return $this->adapter;
    }
    
    protected function getProjAdapter()
    {
        if (empty($this->projAdapter)) {
            $this->projAdapter = new \Home\Model\PaintProjModel(6000);
        }
    
        return $this->projAdapter;
    }
    
    public function _initialize ()
    {
        $this->today = date("Y-m-d");
        
        if (isset($_REQUEST['subProj']) && $_REQUEST['subProj']) {
            $this->subProj = $_REQUEST['subProj'];
        }
        
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
        $this->assign("subProjs", $adapter->getSubProjs());
        $this->assign("totalDmds", $adapter->getTotalDayDmds());
        $this->assign("capacities", $adapter->getCapacities());
        $this->assign("unusedCapacities", $adapter->getAvailCapacities());
        $this->assign("totalProds", $adapter->getTotalDayProds());
        $this->display();
    }

    
    public function getProjDirDailyCaps()
    {
        if (empty($this->projDirDailyCaps)) {
            $adapter = $this->getProjAdapter();
            $adapter->setDailyStartDate($this->today);
            $this->projDirDailyCaps = $adapter->getProjDirDailyCaps();
        }
        

    
        return $this->projDirDailyCaps;
    }
    
   

    public function setDailyRacks ($startDate = '')
    {
        if (empty($startDate))  {
            $startDate = $this->today;
        }
        
        $dates = \DateHelper::getActiveDatesFrom($startDate);
        $fmdates = [];
        foreach ($dates as $date) {
            $fmdates[$date] = \DateHelper::getFormattedWeekday($date, 'd');
        }
        
        
        $adapter = $this->getProjAdapter();
        $adapter->setDailyStartDate($startDate);
        
        $this->assign("startDate", $startDate);
        $this->assign("dates", $dates);
        $this->assign("fmdates", $fmdates);
        $this->assign("projsInfo", $adapter->getProjsDailyInfo());
        
        $this->display();
        
    }

    public function updateDailyHours()
    {
        $allData = [];
        foreach ($_REQUEST as $key => $drk) {
            if (stripos($key, "pdh#") === 0) {
                list($r, $site, $no, $date) = explode("#", $key);
                $allData[] = [
                        "site" => $site,
                        "no"   => $no,
                        "date" => $date,
                        "drk" => $drk
                ]; 
                
            }
        }
        


        $model = new Model();
        $model->startTrans();
        try {
            $adapter = $this->getProjAdapter();
            $adapter->updateProjsDailyHour($allData);
            
            $model->commit();
            $err = false;
            $msg = "日挂具数更新成功";
        } catch (\Exception $e) {
            $model->rollback();
            $err = true;
            $msg = $e->getMessage();
        }
        
        $data = new \stdClass();
        $data->statusCode = $err ? 300 : 200;
        $data->message = $msg ?  $msg : "未进行任何操作";
        $this->ajaxReturn($data);
    }
    
}