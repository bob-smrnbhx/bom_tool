<?php

namespace Home\Model;

class PaintProjModel 
{
    private $_paintProjMstrModel;
    private $_paintProjDailyModel;
    private $_paintProjDailyCapModel;
    
    const projShiftSHour = 12;
    
    protected $conds = [];
    
    
    
    protected $projsInfo = [];
    
    protected function getPaintMstrModel()
    {
        if (empty($this->_paintProjMstrModel)) {
            $this->_paintProjMstrModel = M("pgp_mstr");
        }
        
        return $this->_paintProjMstrModel;
    }
    
    protected function getPaintDailyModel()
    {
        if (empty($this->_paintProjDailyModel)) {
            $this->_paintProjDailyModel = M("pgp_daily");
        }
    
        return $this->_paintProjDailyModel;
    }
    
    protected function getPaintProjDailyCapModel()
    {
        if (empty($this->paintProjDailyCapModel)) {
            $this->paintProjDailyCapModel = M("pgp_dcap");
        }
    
        return $this->paintProjDailyCapModel;
    }
    
    public function __construct($site = '', $subProj = '')
    {
        if ($site) {
            $this->addConds([
                    "pgp_site" => $site
            ]);
        }
        
        if ($subProj) {
            $this->addConds([
                    
            ]);
        }
        
    }
    
    public function addConds(array $newConds)
    {
        $this->conds = array_replace($this->conds, $newConds);
    }
    
    protected function getConds()
    {
        return $this->conds;
    }
    
    
    public function setDailyStartDate($date)
    {
//         $this->addConds([
//                 "pgp_date" => ["EGT", $date]
//         ]);
    }
    
    public function getProjsDailyInfo()
    {
        if (empty($this->projsInfo)) {
            $result = $this->getPaintProjDailyCapModel()->where($this->getConds())->order("pgp_no")->select();
            foreach ($result as $row) {
                $pno = $row["pgp_no"];
                $dir = $row["pgp_lr"];
                $date = $row["pgp_date"];
                
                if (!isset($this->projsInfo[$pno])) {
                    $this->projsInfo[$pno] = [
                            "site"      => $row["pgp_site"],
                            "line"      => $row["pgp_line"],
                            "no"        => $pno,
                            "name"      => $row["pgp_name"],
                            "per"       => $row["pgp_per"],
                            "dirCaps"   => [],
                            "dirFullRacks"  => [],
                            "dracks"    => []
                    ];
                }
                
                if (!is_null($date) && !isset($this->projsInfo[$pno]['dracks'][$date]) && $row["pgp_drk"]) {
                    $this->projsInfo[$pno]['dracks'][$date] = intval($row["pgp_drk"]);
                }
                
                if (!isset($this->projsInfo[$pno]['dirFullRacks'][$dir])) {
                    $this->projsInfo[$pno]['dirFullRacks'][$dir] = $row["pgp_rack"];
                }
                
            }
            
        }

        
        return $this->projsInfo;
    }
    
    public function getProjDirDailyCaps()
    {
        $projDirDailyCaps = [];
        $items = $this->getProjsDailyInfo();
        foreach ($items as $item) {
            foreach ($item["dirFullRacks"] as $dir => $fullRack) {
                $per = $item["per"];
                foreach ($item['dracks'] as $date => $rack) {
                    $projDirDailyCaps[$item["no"]][$dir][$date] = $rack * $per;
                }
            }
        }
        
        return $projDirDailyCaps;
    }
    
    
    public function updateProjsDailyHour($allData)
    {
        // convert data keys to db keys
        $allDbData = [];
        foreach ($allData as $data) {
            $dbData = [];
            foreach ($data as $key => $item) {
                $key = "pgp_$key";
                $dbData[$key] = $item;
            }
            $allDbData[] = $dbData;

        }
        
        $model = $this->getPaintDailyModel();
        
        foreach ($allDbData as $data) {
            $where = $bind = [];
            $where['pgp_site'] = ':pgp_site';
            $where['pgp_no']   = ':pgp_no';
            $where['pgp_date'] = ':pgp_date';
            
            $bind[':pgp_site']    =  array($data["pgp_site"],\PDO::PARAM_STR);
            $bind[':pgp_no']      =  array($data["pgp_no"],\PDO::PARAM_STR);
            $bind[':pgp_date']    =  array($data["pgp_date"],\PDO::PARAM_STR);
            
            
            if ($model->where($where)->bind($bind)->count() != 0) {
                $model->where($where)->bind($bind)->save($data);
            } else {
                $model->add($data);
            }
            
        }
    }
    
    

}