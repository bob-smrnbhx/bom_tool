<?php
namespace Home\Controller;
use Think\Controller;
use Think\Model;

class RealtimeDataController extends ToolController
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

 
 

    public function updateItems ()
    {
        set_time_limit(3000);
        
        $pmodel = new Model();
        
        $err = '';
        $msg = '';
        try {
            foreach ($_REQUEST["data"] as $item) {
                switch ($item) {
                    case "stock":
                        $stockWarnings = [];
                        $this->importInData(true, $_REQUEST["site"]);
                        break;
                    case "stockDetail":
                        $stockWarnings = [];
                        $this->importInDetailData(true, $_REQUEST["site"]);
                        break;
                    case "tran":
                        $tranWarnings = [];
                        $this->importTransExcel(true, $_REQUEST["site"]);
                        break;
                }
            
            }
            
            $sql = 'update xy_ptp_det set ptp_isdmrp=1,ptp_iswmrp=1,ptp_ismmrp=1';
            // 如果即时数据分地点，分地点重置ismrp标志
            switch ($_REQUEST["site"]) {
                case 1000:
                    $sql .= " where ptp_site='1000'";
                    break;
                case 6000:
                    $sql .= " where ptp_site='6000'";
                    break;
            }
            $pmodel->execute($sql);
            
            $pmodel->commit();
        } catch (\Exception $e) {
            $pmodel->rollback();
            $msg = $e->getMessage();
        }

//         if ($err) {
//             $this->error($msg, '', 30);
//         } else {
//             $this->success($msg, '', 30);
//         }
        
        $data = new \stdClass();
        $data->statusCode = $err ? 300 : 200;
        $data->message = $msg ?  $msg : "导入成功";
        $this->ajaxReturn($data);

    }
}