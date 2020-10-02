<?php


namespace Home\Controller;
use Think\Controller;

/**
 * 物料分解控制器
 * @author wz
 *
 */
class ProdecompController extends ProplanController
{
    protected $_allowedCondFields = [
            "comp_site", "comp_part", "comp_line",  "comp_cpart" , "comp_desc1", "comp_desc2", "comp_promo", "comp_buyer", "comp_vend", "comp_ismrp"
    ];
    
    
    protected $_model;
    
    protected function getModel()
    {
        return $this->_model;
    }
    
    public function _initialize ()
    {
        parent::_initialize();
        
        $this->_model = M('prodecomp');
    }


    
    protected function _search ()
    {
        if (!isset($_REQUEST["par_id"])) {
            $par_ids = [];
        } else {
            $par_ids = array_values(array_filter(array_map("intval", explode("_", $_REQUEST["par_id"]))));
        }
        
        $map = [
                "comp_pm_code" => "P",
                "comp_status" => 'AC',
                
        ];
        
        switch($_REQUEST["site"]) {
            case 1000:
                $map["par_site"] = '1000';
                break;
            case 6000:
                $map["par_site"] = '6000';
                break;
        }
        
        // 只显示采购员自己关联的零件的采购运算
        if (self::getUserType() == 'P') {
            $map["comp_buyer"] = self::getCurrentBuyer();
        }
        
        if (count($par_ids) == 1) {
            $map["par_id"] = $par_ids[0];
        } else if (count($par_ids) > 1) {
            $map["par_id"] = ['IN', $par_ids];
        }
        
        if (isset($_REQUEST["par_f"]) && !empty($_REQUEST["par_f"]) && isset($_REQUEST["par_v"]) && !empty($_REQUEST["par_v"])) {
            $field = trim($_REQUEST["par_f"]);
            $val = trim($_REQUEST["par_v"]);
            $map[$field] = ["like", "$val%"];
        
        }
    
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
    
    
    
    public function index ()
    {
        $model = $this->getModel(); 
        $map = $this->_search();

        // thinphp3.2的select()方法极度耗用内存，这里直接使用PDO来减少内存开销，干脆使用模型来构造SQL查询字符串
        $dbName = C("DB_NAME");
        $pdo = new \PDO("mysql:dbname=$dbName;host=127.0.0.1", 'swc',  'swc');
        $pdo->query('set names utf8');
        
        
        if (! empty($model)) {  
            if (isset($_REQUEST['orderField'])) {
                $orderField = $_REQUEST['orderField'];
            }
            if (method_exists($this, '_befor_index')) {
                $this->_befor_index();
            }
            if ($orderField == '') {
                $orderField = $model->getPk();
            }
            
            // 排序方式默认按照倒序排列
            // 接受 sort参数 0 表示倒序 非0都 表示正序
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

            $dbprefix = C('DB_PREFIX');
            //$prodecompdb = $dbprefix . "prodecomp";
            switch($_REQUEST["site"]) {
                case 1000:
                    $prodecompdb = $dbprefix . "prodecompNB";
                    break;
                case 6000:
                    $prodecompdb = $dbprefix . "prodecompCQ";
                    break;
            }
            
            
            // 取得满足条件的记录数
//             $count = $model->where($map)->count("distinct comp_part");   
            
            // 使用专用视图获取子部件总数
            //$count = M("prodecomp_comp_distinct")->where($map)->count();
            
            
            
            $count = count($model->distinct(true)->field("comp_part")->where($map)->select());
            
            $dates = $this->getDates();
            $dheaders = $this->getFormattedDatesMap();

            
            if ($count > 0) {
                $numPerPage = 20;
                

//                 $interPsCompTable = $model->fetchSql(true)->distinct(true)->field("comp_part as inter_comp_part")
//                 ->where($map)->order("$orderField $sort")->page($pageCurrent, $numPerPage)->select();
//                 $result = $model->join("right join ($interPsCompTable) as intercomps on $prodecompdb.comp_part=intercomps.inter_comp_part")
//                 ->order("$orderField $sort")
//                 ->select();  

                // 获取该分页的子零件号集合
                $interpsComps = [];

                $interpsCompsResult = $model->distinct(true)->field("comp_part")
                ->where($map)->order("$orderField $sort")->page($pageCurrent, $numPerPage)->select();
                
                foreach ($interpsCompsResult as $item) {
                    $interpsComps[] = $item['comp_part'];
                }


                
//                 $result = $model->where(["comp_part" => ["in", $interpsComps]])
//                 ->order("$orderField $sort")->select();
                
                // thinphp3.2的select()方法极度耗用内存，这里直接使用PDO来减少内存开销
                $compConds = ["comp_part" => ["in", $interpsComps]];
                if (isset($_REQUEST["site"])) {
                    $compConds["comp_site"] = intval($_REQUEST["site"]);
                }
                $sql = $model->fetchSql(true)
                ->where($compConds)
                ->order("$orderField $sort")->select();
                $result = $pdo->query($sql, \PDO::FETCH_ASSOC);
 
 

                //echo G('begin','end','m').'kb';
 
                //G('begin');
                $parts = self::convertToDecompsFromDbResult($result);
                //G('end');
                //echo G('begin','end').'s';
                //file_put_contents("tmp.txt", var_export($parts, true));
    
                // sort by drp_date, wrp_week, mrp_month;
                foreach ($parts as &$rpart) {
                    isset($rpart["drps"]) && ksort($rpart["drps"]);
                    isset($rpart["wrps"]) && ksort($rpart["wrps"]);
                    isset($rpart["mrps"]) && ksort($rpart["mrps"]);
                }
                
                
                // trim the year of dates header
                array_walk($datesTh, function(&$header) {
                    $header = substr($header, 5);
                });
                
                

            }
            
            $this->assign("dates", $dates);
            $this->assign("dheaders", $dheaders);
            $this->assign("parts", $parts);
            $this->assign('totalCount', $count); // 数据总数
            $this->assign('currentPage',
                    ! empty($_REQUEST[C('VAR_PAGE')]) ? $_REQUEST[C('VAR_PAGE')] : 1); // 当前的页数，默认为1
            $this->assign('numPerPage', $numPerPage); // 每页显示多少条
            $this->assign('pageCurrent', $pageCurrent);
            cookie('_currentUrl_', __SELF__);

            
            switch($_REQUEST["site"]) {
                case 1000:
                    $this->display("index-nb");
                    break;
                case 6000:
                    $this->display("index-cq");
                    break;
            }
            
        }
    }
    
    
    static function convertToDecompsFromDbResult($result)
    {   //G('begin');
        $parts = []; 
        foreach ($result as $item) {
            $uid = $item["comp_part"] . $item["comp_site"];
            $qtyPer = floatval($item["ps_qty_per"]);
            $yldPct = floatval($item["par_yld_pct"]);
            if (!isset($parts[$uid])) {
                //$parts[$comp]["comp_part"] = $item["comp_part"];
                //$parts[$comp] = $item;
                $parts[$uid] = [
                        "comp_part" => $item["comp_part"],
                        "comp_site" => $item["comp_site"],
                        "comp_line" => $item["comp_line"],
                        "comp_cpart" => $item["comp_cpart"],
                        "comp_desc1" => $item["comp_desc1"],
                        "comp_desc2" => $item["comp_desc2"],
                        "comp_promo" => $item["comp_promo"],
                        "comp_vend" => $item["comp_vend"],
                        "comp_buyer" => $item["comp_buyer"],
                        "comp_ismrp" => (bool)$item["comp_ismrp"],
                ];
            }
            
//             if (!isset($parts[$uid]["par_parts"]) || !in_array($item["par_part"], $parts[$uid]["par_parts"]) ) {
//                 $parts[$uid]["par_parts"][] = $item["par_part"];
//                 $parts[$uid]["ps_qty_pers"][$item["par_part"]] = $qtyPer;
//             }

            if (!isset($parts[$uid]["par_parts"]) || !isset( $parts[$uid]["par_parts"][$item["par_part"]] ) ) {
                $parts[$uid]["par_parts"][$item["par_part"]] = $item["par_part"];
                $parts[$uid]["ps_qty_pers"][$item["par_part"]] = $qtyPer;
                $parts[$uid]["par_yld_pcts"][$item["par_part"]] = $yldPct;
            }
            
            if (!isset($parts[$uid]["drps"][$item["drps_date"]][$item["par_part"]])) {
                $parts[$uid]["drps"][$item["drps_date"]][$item["par_part"]] = [
                        'drps_qty' => floatval($item["drps_qty"]),
                        'drps_id' => $item["drps_id"],
                        'drps_ismrp' => (bool)$item["drps_ismrp"],
                ];
                
            } 
    
        }
        //echo G('begin','end').'s';
        
        // accumulate rps for comps
        foreach ($parts as &$cpart) {
            foreach ($cpart["drps"] as $d => $rps) {
                $pqty = 0;
                $pqtys = [];
                $rpsids = [];
                $date = '';
                $ctitles = [];
                $ismrp = false;
                $cqty = 0;
                foreach ($rps as $psPar => $rp) {
                    //$cqty += $rp["drps_qty"] * $cpart["ps_qty_pers"][$psPar] / ($cpart["par_yld_pcts"][$psPar] / 100);
                    $cqty += $rp["drps_qty"] * $cpart["ps_qty_pers"][$psPar];
                    if ($rp["drps_qty"]) {
                        $info = "父物料：$psPar, 单件需用量：" . $cpart["ps_qty_pers"][$psPar] . ", 总用量: " . floatval($rp["drps_qty"]);
                        if ($cpart["par_yld_pcts"][$psPar] != 100) {
                            //$info .= ", 合格率: " . $cpart["par_yld_pcts"][$psPar] . '%';
                        }
                        $ctitles[] = $info;
                        
                    }
                    
                    $pqtys[$psPar] = floatval($rp["drps_qty"]);
                    $rpsids[] = $rp["drps_id"];
                    
                    // date and *rps_ismrp would always be the same in the closest loop
                    $date = $rp["drps_date"];   
                    if ($rp["drps_ismrp"]) {
                        $ismrp = true;
                    }
                    
                }
                

                $cqty = round($cqty);
                $cpart["drps"][$d] = [
                        "drps_cqty" => $cqty,
                        "drps_title" => implode("&#10;", $ctitles),
                        "u_pqtys" => $pqtys,
                        "drps_ids" => $rpsids,
                        "drps_date" => $date,
                        "drps_ismrp" => $ismrp      
                ];
            }
        }
         
        // sort by drp_date ;
        foreach ($parts as &$rpart) {
            $rpart["ps_qty_per_sum"] = array_sum($rpart["ps_qty_pers"]);
            isset($rpart["drps"]) && ksort($rpart["drps"]);
        }


        return $parts;
    }
    
    
    
}