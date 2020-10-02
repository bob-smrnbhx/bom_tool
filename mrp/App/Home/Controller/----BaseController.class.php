<?php
namespace Home\Controller;
use Think\Controller;
use Think\Model;

class BaseController extends CommonController
{
    
    public function _initialize ()
    {
        parent::_initialize();
    }

    
    public function showItems ()
    {
        $this->display();
    }
    public function index()
    {
        $this->display();
    }

    public function getBaseData ($filname)
    {
        $dir = "./Public/BaseCsv/";
        $fileExportFile = $dir . $filname . ".csv";
        $fp = fopen($fileExportFile, "r");
        if ($fp == false) {
            throw new \Exception("文件没有找到！");
        }
        $heads = [];
        $dataall = [];
        while (($row = fgetcsv($fp, 1000, ",")) !== false) {
            if (!$heads) {
                $heads = $row;
            } else {
                $data = [];
                foreach ($heads as $key => $fd) {
                    $data[$fd] = $row[$key];
                }
                $dataall[] = $data;
            }
        }
        return $dataall;
    }

    public function insertData ($dataall, $tb)
    {
        $model = D($tb);
        $model->startTrans();
        $model->where("1")->delete();
        $model->addAll($dataall);
        echo $model->getLastSql();
        
        $pmodel = new Model();
        $sql = 'update xy_ptp_det set ptp_ismrp=1,ptp_isdmrp=1,ptp_iswmrp=1,ptp_ismmrp=1;';
        $pmodel->execute($sql);
        
        //$model->commit();
        
    }

    public function updateItems ()
    {
        set_time_limit(3000);
        $fileExportFileName = $_POST["chk"];

        foreach ($fileExportFileName as $key => $fe) {
            $getcsv = $this->getBaseData($fe);
            //$getcsv = array_slice($getcsv, 0, 10);
            $this->insertData($getcsv, $fe);
        }
        
        $this->success("导入数据成功！");
    }
}