<?php
namespace Home\Controller;
use Think\Controller;
use Think\Model;

class BaseDataController extends ToolController
{
    protected $_dataTypeNames = [
            "ptp" => "物料",
            "bom" => "BOM",
            "lnd" => "产线",
            "in"  => "库存",
            "vd" => "供应商",
            "cm" => "客户",
            "pod" => "采购订单",
            "sod" => "销售订单",
            "cal" => "供应商日历",
            "rps" => "生产计划",
            "pog" => "在途订单量"
    ];
    
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

    public function updateTbls ($reMrp = false)
    {
        set_time_limit(6000);
        
        
        $startTime = microtime(true);
        $err = false;
        $msg = '';
        $dataTypeNames = [];
        $model = new Model();
        $model->startTrans();
        try {
            foreach ($_POST["chk"] as $key => $type) {
                $dataTypeNames[] = $this->_dataTypeNames[$type];
                $method = "import" . ucfirst($type). "Data";
                $this->$method(true);
            }
            
            // 重置ptp_det表所有记录为需要计算mrp状态。
            $pmodel = new Model();
            $sql = 'update xy_ptp_det set ptp_ismrp=0,ptp_isdmrp=1,ptp_iswmrp=1,ptp_ismmrp=1;';
            $pmodel->execute($sql);
            
            $msg = implode(",", $dataTypeNames) . " 基础数据" . "同步完成";
            $model->commit();
        } catch (\Exception $e) {
            $model->rollback();
            $err = true;
            $msg = $e->getMessage();
        }
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $msg .= "<br />耗时：$duration 秒";
        
        $data = new \stdClass();
        $data->statusCode = $err ? 300 : 200;
        $data->message = $msg;
        $this->ajaxReturn($data);
        
    }
}