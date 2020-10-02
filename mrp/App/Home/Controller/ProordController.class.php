<?php

 

namespace Home\Controller;
use Think\Controller;

class ProordController extends ProvendController
{
   protected $_today;

   public function _initialize() 
   {
        parent::_initialize();
        $this->_today = date("Y-m-d");
   }
   
   protected function getMrpConds ()
   {
       $map = self::$baseConds;
   
       $map["tran_date"] = $this->_today;
   
       return $map;
   }
   
   protected function _search ()
   {
       $map = $this->getMrpConds();
   
   
       if (isset($_REQUEST["conds"])) {
           foreach ($_REQUEST["conds"] as $field => $val) {
               $val = trim($val);
               $map[$field] = ["like", "$val%"];
   
           }
       } else if (isset($_REQUEST["f"]) && !empty($_REQUEST["f"]) && isset($_REQUEST["v"]) && !empty($_REQUEST["v"])) {
           $field = trim($_REQUEST["f"]);
           $val = trim($_REQUEST["v"]);
           $map[$field] = ["like", "$val%"];
   
       }
   
       return $map;
   }
	
   /**
    * 按照供应商代码来进行分页显示活动版本的在途数据
    */
   public function index ()
   {
       $model = $this->getMrpModel();
       $map = $this->_search();
   
       if (isset($_REQUEST['orderField'])) {
           $orderField = $_REQUEST['orderField'];
       }
       if ($orderField == '') {
           $orderField = $model->getPk();
       }
       if (isset($_REQUEST['orderDirection'])) {
           $sort = $_REQUEST['orderDirection'];
       }
       if ($sort == '') {
           $sort = 'asc';
       }
   
       if (isset($_REQUEST['pageCurrent'])) {
           $pageCurrent = $_REQUEST['pageCurrent'];
       }
       if ($pageCurrent == '') {
           $pageCurrent = 1;
       }
   
       $model->startTrans();
       // 获取匹配生产计划的供应商
       $vendAddrsResult = $model->distinct(true)->field("comp_vend")->order("$orderField $sort")->where($map)->select();
       foreach ($vendAddrsResult as $row) {
           $vends[] = $row["comp_vend"];
       }
       $count = count($vends);
   
       if ($count > 0) {
           $vend = $vends[$pageCurrent - 1];
   
   
           // 不要直接使用 _search()返回的可能包含多余筛选的条件，直接使用基础条件。
           $map = $this->getMrpConds();
           $map["vd_addr"] = $vend;
           // 获取用于mrp计算的最近版本（尚未审核或最新已审核）数据
           $activeNbr = $this->getActiveNbrOfVend($vend);
           $map["tran_nbr"] = $activeNbr ? $activeNbr : ["exp", "is null"];
   
           $result = $model->where($map)->order("$orderField $sort")->select();
            
   
           $datesTh = [];
           $parts = self::convertToCompsInfoFromDbResult($result);
   
           $model->commit();
   
           $numPerPage = count($parts);
   
   
           $this->assign("today", $this->_today);
           $this->assign("datesTh", $datesTh);
           $this->assign("parts", $parts);
           $this->assign("vend", $vend);
   
       }
       $this->assign('totalCount', $count);
       $this->assign('currentPage', ! empty($_REQUEST[C('VAR_PAGE')]) ? $_REQUEST[C('VAR_PAGE')] : 1);
       $this->assign('numPerPage', $numPerPage);
       $this->assign('pageCurrent', $pageCurrent);
       cookie('_currentUrl_', __SELF__);
   
       $this->display();
   }
    
    static protected function convertToCompsInfoFromDbResult($result)
    { 
        $compsInfo = [];
        foreach ($result as $row) {
            $uid = $row["comp_vend"] . "-" . $row["comp_part"]. "-" . $row["comp_site"];
            if (!isset($compsInfo[$uid])) {
                $compsInfo[$uid] = $row;
            }
    

            if (!isset($compsInfo[$uid]["tran_qtys"][$row["tran_date"]])) {
                $compsInfo[$uid]["tran_qtys"][$row["tran_date"]] = floatval($row["tran_qty"]);
            }
            
//             if (!isset($compsInfo[$uid]["shop_qtys"][$row["tran_date"]])) {
//                 $compsInfo[$uid]["shop_qtys"][$row["tran_date"]] = floatval($row["tran_ord_qty"]);
//             }
        }

        return $compsInfo;
    }


    
 
    
 
}