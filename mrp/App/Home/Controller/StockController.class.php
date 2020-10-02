<?php

/**
 *      产品管理控制器
 *      [X-Mis] (C)2007-2099  
 *      This is NOT a freeware, use is subject to license terms
 *      http://www.xinyou88.com
 *      tel:400-000-9981
 *      qq:16129825
 */

namespace Home\Controller;
use Think\Controller;

class StockController extends ToolController
{
    protected $_allowedCondFields = [
            "ptp_site", "ptp_part",  "ptp_cpart" , "ptp_desc1", "ptp_desc2", "ptp_pm_code", "ptp_rop"
    ];

   public function _initialize() {
        parent::_initialize();
        $this->dbname = "ptp_stock";
        C('PERPAGE', 20);
    }
	


   protected function _search ()
   {
       if (isset($_REQUEST["conds"])) {
           foreach ($_REQUEST["conds"] as $field => $val) {
               if (in_array(trim($field), $this->_allowedCondFields)) {
                   $val = trim($val);
                   $map[$field] = ["like", "$val%"];
               }
   
           }
       } else if (isset($_REQUEST["f"]) && !empty($_REQUEST["f"]) && isset($_REQUEST["v"]) && !empty($_REQUEST["v"])) {
           $field = trim($_REQUEST["f"]);
           if (in_array($field, $this->_allowedCondFields)) {
               $val = trim($_REQUEST["v"]);
               $map[$field] = ["like", "$val%"];
           }
   
       }


       return $map;
   }
   
   
   protected function getUploadeFile ()
   {
       $upload = new \Think\Upload();
       $upload->maxSize   =     30000000 ;
       $upload->exts      =     array('xls', 'xlsx');
       $upload->rootPath  =     './Uploads/stocks/';
       $upload->autoSub = true;
       $upload->subName = array('date','Ymd');
       $upload->saveName = 'stock_' . time().'_'.mt_rand();;
   
       $info = $upload->upload();
       if(!$info) {
           throw new \Exception($upload->getError());
       } else {
           if (count($info) > 1) {
               throw new \Exception("错误：一次只允许上传一个库存数据文件");
           }
           $file = current($info);
           return $upload->rootPath . $file['savepath'].$file['savename'];
       }
   }
   
   
   public function importStocks ($override = false, &$warnings = [], $type = '')
   {
       $start = time();
   
       switch ($type) {
           case self::P_TYPE:
               $type = 'P';
               break;
           case self::L_TYPE:
               $type = 'L';
               break;
           default:
               $type = '';
       }
   
       $override = filter_var($override,FILTER_VALIDATE_BOOLEAN);
   
       $stock = M("in_mstr");
       $stock->startTrans();
       $err = false;
       $msg = '';
       try {
           $filepath = $this->getUploadedRpsFile();
           $rows = $this->xlsin($filepath);
           array_walk_recursive($rows, function(&$val) {
               $val = trim($val);
           });
           
           // read the first row as header.
           $heads = array_shift($rows);
           if (count($heads) < 3) {
               throw new \Exception("invalid stock data excel format");
           }
           
           
           $allData = [];
           foreach ($rows as $row) {
               $allData[] = [
                       "in_site" => $row["A"],
                       "in_part" => $row["B"],
                       "in_qty_oh" => floatval($row["C"]),
                       "in_date" => $heads["C"],
               ];
           }
       
       

           $stock->lock(true);
           if ($override) {
               $stock->where("1")->delete();
           }
       
           $ptp = D("PtpDet");
           $nonMatchParts = [];
           foreach ($allData as $data) {
               // check part existence and verify part type.
               $where = $bind = [];
               $where['ptp_part'] = $data["in_part"];
               $type && $where['ptp_pm_code'] = $type;
               $bind[':ptp_part']    =  array($data["in_part"],\PDO::PARAM_STR);
               $type && $bind[':ptp_pm_code']    =  array($type,\PDO::PARAM_STR);
               if ($ptp->where($where)->bind($bind)->count() == 0) {
                   $nonMatchParts[] = $data["in_part"];
                   continue;
               }
       
       
               $stockCond = [
                       'in_site' => $data["in_site"],
                       'in_part' => $data["in_part"],
               ];
               if ($stock->where($stockCond)->count()) {
                   $stock->where($stockCond)->save($data);
               } else {
                   $stock->add($data);
               }
           }
       
           if ($nonMatchParts) {
               $warn = "如下";
               if ($type) {
                   $warn .= " $type 类型";
               }
               $warn .= "零件号在物料主数据表中不存在： <br />";
               $warn .= implode(", ", $nonMatchParts);
               $warnings[] = $msg = $warn;
           }
           $stock->commit();
       } catch (\Exception $e) {
           $stock->rollback();
           $err = true;
           $msg = $e->getMessage();
       }

        $data = new \stdClass();
        $data->statusCode = $err ? 300 : 200;
        $data->message = $msg ?  $msg : "未进行任何操作";
        $this->ajaxReturn($data);
   
   }
   
   public function showStockUpload()
   {
       $this->display();
   }

   public function outxls() {
		$model = D($this->dbname);
		$map = $this->_search();
		if (method_exists($this, '_filter')) {
			$this->_filter($map);
		}
		$list = $model->where($map)->field('id,name,fenlei,jiage,sjiage,type,ruku,kucun,chuku,title,uname,addtime,updatetime')->select();
	    $headArr=array('ID','产品名称','产品分类','采购价格','销售价格','计量单位','入库数量','库存数量','出库数量','型号规格','添加人','添加时间','更新时间');
	    $filename='产品管理';
		$this->xlsout($filename,$headArr,$list);
	}
	
 
	

}