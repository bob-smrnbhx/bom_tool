<?php
namespace Home\Controller;
use Think\Controller;
use Think\Model;
import("Date.DateHelper");

class QadDataController extends CommonController
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
            "rps" => "生产计划"
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

    public function exportVendsSchdCim ($cim_line_sep = "\n")
    {
        $initStockDate = M("ptp_stock")->max("in_date");
    
        $map = [];
        switch($_REQUEST["site"]) {
            case 1000:
                $map["pod_site"] = '1000';
                break;
            case 6000:
                $map["pod_site"] = '6000';
                break;
        }
    
        $model = M("tran_pod");
        $results = $model->distinct(true)->where($map)->order("pod_vend, pod_site, pod_nbr, tran_nbr, pod_line, tran_date")->select();
    
        $groupedResults = [];
        foreach ($results as $item) {
            $uuid = "$item[pod_nbr]-$item[pod_vend]-$item[pod_site]-$item[pod_part]-$item[pod_line]-$item[tran_nbr]";
            if (!isset( $groupedResults[$uuid])) {
                $groupedResults[$uuid] = [
                        "pod_nbr" => $item["pod_nbr"],
                        "pod_vend" => $item["pod_vend"],
                        "pod_site" => $item["pod_site"],
                        "pod_part" => $item["pod_part"],
                        "pod_line" => $item["pod_line"],
                        "tran_nbr" => $item["tran_nbr"],
                        "pod_firm_days" => $item['pod_firm_days']
                ];
            }
            $groupedResults[$uuid]["qtys"][$item["tran_date"]] = $item["tran_qty"];
    
        }
    
        //dump($groupedResults);
    
        $content = '';
        $today = date('m/d/y');
        foreach ($groupedResults as $itemGroup) {
            $content .= "\"$itemGroup[pod_nbr]\" \"$itemGroup[pod_part]\" \"$itemGroup[pod_vend]\" \"$itemGroup[pod_site]\" \"$itemGroup[pod_line]\"" . $cim_line_sep;
            $content .= "\"$itemGroup[tran_nbr]\"" . $cim_line_sep;
            $content .= "\"No\" \"0\" \"$today\"" . $cim_line_sep;
    
            foreach ($itemGroup["qtys"] as $date => $qty) {
                $schdDelayedDay = (strtotime($date) - strtotime($initStockDate)) / 86400;
                $state = $schdDelayedDay <= $itemGroup['pod_firm_days'] ? "F" : "P";
    
                $date = date('m/d/y', strtotime($date));
                $content .= "\"$date\"" . $cim_line_sep;
                $content .= "\"$qty\" \"$state\"" . $cim_line_sep;
            }
    
            $content .= "." . $cim_line_sep;
            $content .= "-" . $cim_line_sep;
            $content .= "Y" . $cim_line_sep;
        }
    
        $fileName = "vschd.cim";
        header('Content-Type: text/plain');
        header("Content-Disposition: attachment;filename=\"$fileName\"");
        header('Cache-Control: max-age=0');
    
        echo $content;
        exit;
    }
    
    public function exportProdSchdCim ($cim_line_sep = "\n")
    {
        $map = [];
        switch($_REQUEST["site"]) {
            case 1000:
                $map["drps_site"] = '1000';
                break;
            case 6000:
                $map["drps_site"] = '6000';
                break;
        }
        
        $curDate = date("Y-m-d");
        $curMonday = \DateHelper::getMondayOfDate($curDate);
        $map["drps_date"] = ["EGT", $curDate];
        $map["drps_type"] = 'd';
    
        $model = M("drps_mstr");
        
        $dates = $model->distinct(true)->field("drps_date")->where($map)->order("drps_date")->getField("drps_date", true);
        $dateExistMap = array_flip($dates);
        $lastDate = end($dates);
        
        $dateGroups = [];
        while (($curSunday = \DateHelper::getDateAfter($curMonday, 6)) <= $lastDate ) {
            $stdDates = \DateHelper::getDatesBetween($curMonday, $curSunday);
            $enDates = [];
            foreach ($stdDates as $stdDate) {
                $enDates[] = date("m/d/y", strtotime($stdDate));
            }
            $dateGroups[] = $enDates;
            $curMonday = \DateHelper::getDateAfter($curSunday, 1);
        }
        

        
        $results = $model->distinct(true)->where($map)->order("drps_site, drps_part, drps_line")->select();
    
        $schds = [];
        foreach ($results as $item) {
            $uuid = "$item[drps_site]-$item[drps_part]-$item[drps_line]";
            $date = date("m/d/y", strtotime($item["drps_date"]));
            if (!isset($schds[$uuid])) {
                $schds[$uuid] = [
                        "site" => $item["drps_site"],
                        "part" => $item["drps_part"],
                        "line" => $item["drps_line"],
                        "prods" => []
                ];
            }
            
            if (!isset($schds[$uuid]['prods'][$date])) {
                $schds[$uuid]['prods'][$date] = intval($item["drps_qty"]);
            }
        }
    
        
        // filter zero-all part plans
        foreach ($schds as $key => $schd) {
            $isAllZero = true;
            foreach ($schd["prods"] as $date => $qty) {
                if ($qty) {
                    $isAllZero = false;
                    break;
                }
            }
            
            if ($isAllZero) {
                unset($schds[$key]);
            }
        }
        
        $content = '';
        $curMonday = $dateGroups[0][0];
        foreach ($schds as $plans) {
            $content .= "\"$plans[part]\" \"$plans[site]\" \"$plans[line]\" \"$curMonday\"" . $cim_line_sep;
            foreach ($dateGroups as $dateGroup) {
                $items = [];
                foreach ($dateGroup as $date) {
                    if (isset($plans["prods"][$date])) {
                        $items[] = "\"" . $plans["prods"][$date] . "\"";
                    } else {
                        $items[] = "-";
                    }
                    
                }
                $content .= implode(" ", $items) .$cim_line_sep;
            }
            $content .= "." .$cim_line_sep;

    
        }
 
        $fileName = "pschd.cim";
        header('Content-Type: text/plain');
        header("Content-Disposition: attachment;filename=\"$fileName\"");
        header('Cache-Control: max-age=0');
    
        echo $content;
        exit;
    }
    
    

}