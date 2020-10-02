<?php


namespace Home\Controller;
use Think\Controller;
use Think\Model;

/**
 * 物料MRP控制器
 * @author wz
 *
 */
class ProWeekMrpCheckController extends ProWeekMrpController
{
    public function index ($asChecker = true)
    {
        parent::index(true);
    }
    

    
    public function confirmVendOrder()
    {
        if (!IS_AJAX) {
            throw new \Exception("invalid post data from none-ajax request");
        }
    
        $ords = array_map("trim", explode(",", $_REQUEST["ords"]));
        if (empty($ords)) {
            return;
        }
    
        $err = false;
        $msg = '';
        $curTime = date("Y-m-d H:i:s");
        $tranModel = $this->getTranModel();
        $tranModel->startTrans();
        try {
            if (!isset($_REQUEST["vend"]) || empty(trim($_REQUEST["vend"]))) {
                throw new \Exception("未提供待审核供应商");
            }
    
            $vend = trim($_REQUEST["vend"]);
    
            // 将当前vend页的所有子部件的活动版本ispass状态置为2，并设置版本号
            $activeNbr = $this->getActiveNbrOfVend($vend);
            $latestNbr = $this->getLatestNbrOfVend($vend);
            $todayMinNbr = intval(date("Ymd") . '01');
            $todayMaxNbr = intval(date("Ymd") . '99');
            // 如果上一个版本号是今天的，则加+1即可, 否则，上一个是前日的版本号或尚无版本号，新建今日初始版本号。
            if ($latestNbr == $todayMaxNbr) {
                $newNbr = $todayMaxNbr;
            } else if ($latestNbr >= $todayMinNbr && $latestNbr < $todayMaxNbr) {
                $newNbr = $latestNbr + 1;
            } else {
                $newNbr = $todayMinNbr;
            }
 

            //$nbrCondStr = $activeNbr ? "tran_nbr = $activeNbr" : "tran_nbr is null";
            $nbrCondStr = "tran_nbr is null";
            $ptpCondStr = "ptp_pm_code='P' AND ptp_status='AC' AND ptp_ord_per>7";
    
            $model = new Model();
            $sql = "update xy_tran_det 
            join xy_vd_mstr on tran_vend=vd_addr 
            join xy_ptp_det on tran_part=ptp_part and tran_site=ptp_site 
            set 
                tran_qty = tran_qty + tran_ord_qty , tran_ord_qty = 0 , 
                tran_nbr = '$newNbr',
                tran_ctime = '$curTime',
                tran_ispass = '2'
            where tran_vend='$vend' 
            and tran_ispass = '1'
            and $nbrCondStr and $ptpCondStr";
            $model->execute($sql);
             
    
            $tranModel->commit();
    
            $msg = "已审核供应商: $vend 的周采购数据";
        } catch (\Exception $e) {
            $tranModel->rollback();
            $err = true;
            $msg = $e->getMessage();
        }
    
    
    
        $data = new \stdClass();
        $data->statusCode = $err ? 300 : 200;
        $data->message = $msg ?  $msg : "未进行任何操作";
        $this->ajaxReturn($data);
    }
    
    
}