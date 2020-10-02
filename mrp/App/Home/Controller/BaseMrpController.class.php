<?php

namespace Home\Controller;
use Think\Controller;
use Think\Model;

class BaseMrpController extends CommonController 
{
    protected $_ptpModel;
    protected $_compParRelationModel;
    
    static protected $compBaseConds = [
            "comp_pm_code" => "P",
            "comp_status" => 'AC',
    
    ];
    
    static protected function getFormattedDate($date, $type = 'd') 
    {
        switch ($type) {
            case 'd':
                $wd = date("N", strtotime($date));
                $map = [
                        1 => "周一",
                        2 => "周二",
                        3 => "周三",
                        4 => "周四",
                        5 => "周五",
                        6 => "周六",
                        7 => "周日",
                ];
                return $map[$wd];
            case 'w':
                return date("W周", strtotime($date));
            case 'm':
                return date("n月", strtotime($date));
            default:
                throw new \Exception("illegal date type provided: $type");
        }
    }
    
    protected function getPtpModel()
    {
        if (empty($this->_ptpModel)) {
            $this->_ptpModel = M("ptp_det");
        }
    
        return $this->_ptpModel;
    }
    

    
    protected function getCompParRelationModel()
    {
        if (empty($this->_compParRelationModel)) {
            $this->_compParRelationModel = M("comp_par");
        }
    
        return $this->_compParRelationModel;
    }
    
    protected function getCompBaseConds()
    {
        return self::$compBaseConds;
    }


    
    
    protected function setParPartMrpFlagsOfIds(array $parPartIds)
    {
        $ptpModel = $this->getPtpModel();
        
        $ptpModel->ptp_mtime = date("Y-m-d H:i:s");
        $ptpModel->ptp_ismrp = true;
        $ptpModel->where(["id" => ["in", $parPartIds]])->save();

    }
    
    protected function setCompMrpFlagsOfParPartIds(array $parPartIds)
    {
        
        
        $compParRelationModel = $this->getCompParRelationModel();
        $compMap = $this->getCompBaseConds();
        $compMap["par_id"] = ["in", $parPartIds];
        $result = $compParRelationModel->where($compMap)->field("comp_id")->select();
        if ($result) {
            $ptpModel = $this->getPtpModel();
            $compIds = [];
            foreach ($result as $row) {
                $compIds[] = $row["comp_id"];
            }

            $ptpModel->ptp_isdmrp = true;
            $ptpModel->where(["id" => ["in", $compIds]])->save();
            
        }
    }
    
    protected function sendMailToCompBuyersOfParPartIds(array $parPartIds)
    {
        $compParRelationModel = $this->getCompParRelationModel();
        $compMap = $this->getCompBaseConds();
        $compMap["par_id"] = ["in", $parPartIds];
        $result = $compParRelationModel->where($compMap)->distinct(true)->field("comp_buyer,comp_part, comp_vend")->select();
        if ($result) {
            $buyerVendComps = [];
            foreach ($result as $row) {
                if ($row["comp_buyer"]{0} != 'P') continue;
                
                $buyerVendComps[$row["comp_buyer"]][$row["comp_vend"]][] = $row["comp_part"];
            }
            $curTime = date("Y-m-d H:i:s");
            if ($buyerVendComps) {
                $userModel = M("user");
                foreach ($buyerVendComps as $buyer => $vendComps ) {
                    $userInfoResult = $userModel->where(["username" => $buyer])->select();
                    if (count($userInfoResult)) {
                        $userInfo = $userInfoResult[0];
                        $userTrueName = $userInfo["truename"];
                        $userEmail = $userInfo["email"];
                        $mailContent = "<h2>$userTrueName ：</h2>";
                        $mailContent .= '<h3>由于生产计划变更， 如下供应商的相关子物料的mrp结果发生改变：</h3><dl>';
                        foreach ($vendComps as $vend => $comps) {
                            $mailContent .= "<dt>供应商：$vend :</dt>";
                            $mailContent .= "<dd>子物料:" . implode(", ", $comps) . "</dd>";
                        }
                        
                        $mailContent .= "</dl>";
                        $mailContent .= "<h3>请使用mrp web系统查询相关供应商代码重新确认结果后提交</h3>";
                        
                        SendMail($userEmail,'生产计划变更：' . $curTime, $mailContent);
                    }

                }
            }
        }

    }
    
    

}