<?php
namespace Home\Controller;
use Think\Controller;
use Think\Model;

class ToolController extends CommonController
{
    const P_TYPE = 'P';
    const L_TYPE = 'L';
    
    const TRANS_EXCEL_FILE_PATH = "./Public/excelData/tran.xlsx";
    const STOCK_EXCEL_FILE_PATH = "./Public/excelData/stock.xlsx";
    const STOCK_DETAIL_EXCEL_FILE_PATH = "./Public/excelData/stock_detail.xlsx";
    
    const PTP_CSV_FILE_PATH = "./Public/plsdata/smrnbhx_ptpdata.csv";
    const PS_CSV_FILE_PATH = "./Public/plsdata/smrnbhx_bomdata.csv";
    const LND_CSV_FILE_PATH = "./Public/plsdata/smrnbhx_lnddata.csv";
    const VD_CSV_FILE_PATH = "./Public/plsdata/smrnbhx_vdata.csv";
    const CM_CSV_FILE_PATH = "./Public/plsdata/smrnbhx_cmdata.csv";
    const POD_CSV_FILE_PATH = "./Public/plsdata/smrnbhx_poddata.csv";
    
    
    const SOD_CSV_FILE_PATH = "./Public/plsdata/smrnbhx_soddata.csv";
    const SOD_830_CSV_FILE_PATH = "./Public/plsdata/smrnbhx_sod830.csv";
    
    const CAL_CSV_FILE_PATH = "./Public/plsdata/smrnbhx_shp.csv";
    const RPS_CSV_FILE_PATH = "./Public/plsdata/smrnbhx_rps.csv";
    const POG_CSV_FILE_PATH = "./Public/plsdata/smrnbhx_pog.csv";
    const DMD_CSV_FILE_PATH = "./Public/plsdata/smrnbhx_dmddata.csv";
    
    const LD_CSV_FILE_PATH = "./Public/plsdata/smrnbhx_indata.csv";
    const LDNB_CSV_FILE_PATH = "./Public/plsdata/smrnbhx1000_indata.csv";
    const LDCQ_CSV_FILE_PATH = "./Public/plsdata/smrnbhx6000_indata.csv";
    
 
    
    public function test()
    {
        //var_dump($this->getDataFromCsv(self::PTP_CSV_FILE_PATH));
    }
    
    protected static function convertFieldsEncoding(array &$arr, $keys)
    {
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        foreach ($keys as $key) {
            $arr[$key] = iconv("gbk", "utf-8", $arr[$key]);
        }
    }
    
    protected function getDataFromCsv ($csvFile)
    {
        $fp = fopen($csvFile, "r");
        if ($fp == false) {
            throw new \Exception("CSV文件: $csvFile 不存在！");
        }
    
        $heads = [];
        $allData = [];
        while (($row = fgetcsv($fp, 1000, ',')) !== false) {
            if (!$heads) {
                $heads = $row;
            } else {
                $data = [];
                foreach ($heads as $key => $fd) {
                    $data[$fd] = $row[$key];
                }
                $allData[] = $data;
            }
        }
    
        return $allData;
    }
    
    protected function insertDataInBatch($tblName, $allData, $step = 100, $override = false, $overrideCond = '1')
    {
        $model = M($tblName);
        $model->lock(true);

        if ($override) {
            $model->where($overrideCond)->delete();
        }

        $startIndex = 0;
        $count = count($allData);
        while ($startIndex <= $count) {
            $segData = array_slice($allData, $startIndex, $step);
        
            $model->addAll($segData, null, true);
            $startIndex += $step;
        }
    }
    
    protected function saveTblRecordTime($tblName, $time)
    {
        $tblRecordModel = M("tbl_uptime");
        $map = [
                "tbl_name" => $tblName
        ];
        $data = [
                "tbl_name" => $tblName,
                "tbl_update_time" => $time
        ];
        if ($tblRecordModel->where($map)->count() == 0) {
            $tblRecordModel->add($data);
        } else {
            $tblRecordModel->where($map)->save($data);
        }
    }
    
    public function importPtpData ($override = false)
    {
        $override = filter_var($override,FILTER_VALIDATE_BOOLEAN);
    
        $allData = $this->getDataFromCsv(self::PTP_CSV_FILE_PATH);
        $dataTime = date('c', filemtime(self::PTP_CSV_FILE_PATH));
       
        // convert essential field values
        foreach ($allData as &$data) {
            $data["ptp_ins_rqd"] = filter_var($data["ptp_ins_rqd"], FILTER_VALIDATE_BOOLEAN);
            if (empty($data["ptp_bom_code"])) {
                $data["ptp_bom_code"] = $data["ptp_part"];
            }
            self::convertFieldsEncoding($data, "ptp_desc1");
        }
        
        $this->insertDataInBatch("ptp_det", $allData, 200, $override);
        $this->saveTblRecordTime('ptp_det', $dataTime);
    }
    
    public function importBomData ($override = false)
    {
        $override = filter_var($override,FILTER_VALIDATE_BOOLEAN);
        
        $allData = $this->getDataFromCsv(self::PS_CSV_FILE_PATH);
        $dataTime = date('c', filemtime(self::PS_CSV_FILE_PATH));
         
        
        foreach ($allData as $key => &$data) {
            // filter empty qty_per records
            if ($data["ps_qty_per"] == 0) {
                unset($allData[$key]);
                continue;
            }
            $data["ps_start"] = $data["ps_start"] == '?' ? '' : $data["ps_start"];
            $data["ps_end"] = $data["ps_end"] == '?' ? '' : $data["ps_end"];
        }
        
        $this->insertDataInBatch("ps_mstr", $allData, 300, $override);
        $this->saveTblRecordTime('ps_mstr', $dataTime);
    }
    
    
    public function importLndData ($override = false)
    {
        $override = filter_var($override,FILTER_VALIDATE_BOOLEAN);
    
        $allData = $this->getDataFromCsv(self::LND_CSV_FILE_PATH);
        
        // use the latest start time for each line item
        $latestAllData = [];
        foreach ($allData as $data) {
            $uuid = $data["lnd_site"] . $data["lnd_part"] . $data["lnd_line"];
            if (!isset($latestAllData[$uuid]) || $data["lnd_start"] > $latestAllData[$uuid]["lnd_start"] ) {
                $latestAllData[$uuid] = $data;
            } 
        }
        

        $latestAllData = array_values($latestAllData);      
        $this->insertDataInBatch("lnd_det", $latestAllData, 200, $override);
    
    }
    
    
    
    public function importVdData ($override = false)
    {
        $override = filter_var($override,FILTER_VALIDATE_BOOLEAN);
    
        $allData = $this->getDataFromCsv(self::VD_CSV_FILE_PATH);
       
        // convert essential field values
        foreach ($allData as &$data) {
            self::convertFieldsEncoding($data, "vd_sort");
        }
        
        $this->insertDataInBatch("vd_mstr", $allData, 100, $override);
    }
    
    public function importCmData ($override = false)
    {
        $override = filter_var($override,FILTER_VALIDATE_BOOLEAN);
    
        $allData = $this->getDataFromCsv(self::CM_CSV_FILE_PATH);
       
        // convert essential field values
        foreach ($allData as &$data) {
            self::convertFieldsEncoding($data, "cm_sort");
        }

        $this->insertDataInBatch("cm_mstr", $allData, 100, $override);
    }
    
    
    public function importPodData ($override = false)
    {
        $override = filter_var($override,FILTER_VALIDATE_BOOLEAN);
    
        $allData = $this->getDataFromCsv(self::POD_CSV_FILE_PATH);
       
        // filter empty pod_site records
        foreach ($allData as $key => &$data) {
            if (empty($data["pod_site"])) {
                //unset($allData[$key]);
                //continue;
                $data["pod_site"] = 1000;
            }
            
            $data["pod_consignment"] = filter_var($data["pod_consignment"],FILTER_VALIDATE_BOOLEAN);
        }
        
        $this->insertDataInBatch("pod_det", $allData, 100, $override);
    }
    
    public function importSodData ($override = false)
    {
        $override = filter_var($override,FILTER_VALIDATE_BOOLEAN);

        $allData = $this->getDataFromCsv(self::SOD_CSV_FILE_PATH);
        
        $yesterday = date("Y-m-d", time() - 86400);
        $overrideCond = "sod_date >= CURDATE() OR sod_date < '$yesterday'";
 
        
        $this->insertDataInBatch("sod_det", $allData, 100, $override, $overrideCond);
       

        $allData = $this->getDataFromCsv(self::SOD_830_CSV_FILE_PATH);
        $p830data = [];
        foreach ($allData as $data) {
            $item["ssod_nbr"] = $data["sod_nbr"];
            $item["ssod_part"] = $data["sod_part"];
            $item["ssod_date"] = $data["sod_date"];
            $item["ssod_qty"] = $data["sod_discr_qty"];
            $p830data[] = $item;
        }


        $this->insertDataInBatch("ssod_det", $p830data, 100, true);
    }
    
    
    public function importCalData ($override = false)
    {
        $override = filter_var($override,FILTER_VALIDATE_BOOLEAN);
    
        $allData = $this->getDataFromCsv(self::CAL_CSV_FILE_PATH);
        
        foreach ($allData as &$data) {
            $data["shop_vend"] = $data["shp_vend"];
            $data["shop_day"] = $data["shp_workday"];
            
            $data["shop_day"] = bindec(str_replace(",", "", $data["shop_day"]));
            
            
            unset($data["shp_vend"], $data["shp_workday"]);
        }
        
        $this->insertDataInBatch("shop_cal", $allData, 30, $override);
    }

    
    
    public function importRpsData ($override = false)
    {
        $override = filter_var($override,FILTER_VALIDATE_BOOLEAN);
    
        $allData = $this->getDataFromCsv(self::RPS_CSV_FILE_PATH);
    
    
    
        $this->insertDataInBatch("rps_mstr", $allData, 100, $override);
    }
    
    public function importPogData ($override = false)
    {
        $override = filter_var($override,FILTER_VALIDATE_BOOLEAN);
    
        $allData = $this->getDataFromCsv(self::POG_CSV_FILE_PATH);
    
    
    
        $this->insertDataInBatch("pog_det", $allData, 100, $override);
    }
    
    

    public function importInData ($override = false, $site = '')
    {
        $override = filter_var($override,FILTER_VALIDATE_BOOLEAN);
    
        switch ($site) {
            case 1000:
                $dataFile = self::LDNB_CSV_FILE_PATH;
                break;
            case 6000:
                $dataFile = self::LDCQ_CSV_FILE_PATH;
                break;
            default:
                $dataFile = self::LD_CSV_FILE_PATH;
        }

        $allData = $this->getDataFromCsv($dataFile);
    
        // 使用库存文件的修改日期，作为库存字段的统一日期。
        $mtime = filemtime($dataFile);
        $mdate = date("Y-m-d", $mtime);
    
    
        // convert field names and add in_date field as the mtime of the csv file.
        foreach ($allData as $key => &$data) {
            if ($data["in_type"] != 'I') {
                unset($allData[$key]);
                continue;
            }
            $data["in_qty_oh"] = $data["in_qty"];
            unset($data["in_qty"]);
            $data["in_date"] = $mdate;
        }
    
    
        switch ($site) {
            case 1000:
                $overrideCond = 'in_site="1000"';
                break;
            case 6000:
                $overrideCond = 'in_site="6000"';
                break;
            default:
                $overrideCond = '1';
        }

        $this->insertDataInBatch("in_mstr", $allData, 100, $override, $overrideCond);
    }
    
    public function importInDetailData ($override = false, $site = '')
    {
        $override = filter_var($override,FILTER_VALIDATE_BOOLEAN);
    
        switch ($site) {
            case 1000:
                $dataFile = self::LDNB_CSV_FILE_PATH;
                break;
            case 6000:
                $dataFile = self::LDCQ_CSV_FILE_PATH;
                break;
            default:
                $dataFile = self::LD_CSV_FILE_PATH;
        }
    
        $allData = $this->getDataFromCsv($dataFile);
    
        // 使用库存文件的修改日期，作为库存字段的统一日期。
        $mtime = filemtime($dataFile);
        $mdate = date("Y-m-d", $mtime);
    
    
        // convert field names and add in_date field as the mtime of the csv file.
        foreach ($allData as $key => &$data) {
            $data["in_qty_oh"] = $data["in_qty"];
            unset($data["in_qty"]);
            $data["in_date"] = $mdate;
            
            if ($data['in_type'] == 'w') {
                $data['in_type'] = 'o';
            } else if ($data['in_type'] == 'n') {
                $data['in_type'] = 'i';
            } else if ($data['in_type'] == 'I') {
                $data['in_type'] = 'a';
            } else {
                unset($allData[$key]);
            }
        }
    


        switch ($site) {
            case 1000:
                $overrideCond = 'in_site="1000"';
                break;
            case 6000:
                $overrideCond = 'in_site="6000"';
                break;
            default:
                $overrideCond = '1';
        }
    
        $this->insertDataInBatch("in_mstr_detail", $allData, 100, $override, $overrideCond);
    }
    
    
    public function importDmdDate ($override = false)
    {
        $override = filter_var($override,FILTER_VALIDATE_BOOLEAN);
        
        $allData = $this->getDataFromCsv(self::DMD_CSV_FILE_PATH);
         
        foreach ($allData as &$data) {
            if ($data['dmd_type'] == '2') {
                $data['dmd_type'] = 'd';
            } else {
                $data['dmd_type'] = 'm';
            }
        }
        
        $this->insertDataInBatch("dmd_det", $allData, 100, $override);
    }
    
    public function importTransExcel ($override = false, $site = '')
    {
        $start = time();
        $override = filter_var($override,FILTER_VALIDATE_BOOLEAN);
       
        
        $rows = $this->xlsin(self::TRANS_EXCEL_FILE_PATH, 0);
        array_walk_recursive($rows, function(&$val) {
            $val = trim($val);
        });
        
        // read the first row as header.
        $heads = array_shift($rows);
        
        $allData = [];
        foreach ($rows as $row) {
            $site = $row["A"];
            $part = $row["B"];
            foreach ($row as $col => $cell) {
                if ($col < 'C' || empty($heads[$col]) || empty($row[$col])) {
                    continue;
                }
                
                $allData[] = [
                        "tran_site" => $site,
                        "tran_part" => $part,
                        "tran_date" => $heads[$col],
                        "tran_qty" => $row[$col]
                ];
            }
        }
        
        
        
        $ptp = M("ptp_det");
        
        $tran = M("tran_det");
        
        
        $tran->startTrans();
        $tran->lock(true);
        if ($override) {
            if ($site) {
                $tran->where("tran_site=" . $site)->delete();
            } else {
                $tran->where("1")->delete();
            }
        }
        
        $unum = $inum = 0;
        foreach ($allData as $data) {
            // find the vend
            $ptpCond = [];
            $ptpCond['ptp_site'] = 1000;
            $ptpCond['ptp_part'] = $data["tran_part"];
            $vend = $ptp->where($ptpCond)->getField("ptp_vend", true)[0];
        
            if ($vend) {
                $data["tran_vend"] = $vend;
            } else {
                $warnings[] = "$data[tran_part]无关联供应商";
                continue;
            }
        
        
        
            $tranCond = [
                    'tran_site' => $data["tran_site"],
                    'tran_part' => $data["tran_part"],
                    'tran_date' => $data["tran_date"],
            ];
            if ($tran->where($tranCond)->count()) {
                $unum += $tran->where($tranCond)->save($data);
            } else {
                $lid = $tran->add($data);
                if ($lid > 0) {
                    $inum++;
                }
            }
        }
    }
    
    

    
    

    public function importStocksExcel ($override = false, $type = '')
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
    
 
        $rows = $this->xlsin(self::STOCK_EXCEL_FILE_PATH, 0);
        array_walk_recursive($rows, function(&$val) {
            $val = trim($val);
        });
        // read the first row as header.
        $heads = array_shift($rows);

        $allData = [];
        foreach ($rows as $row) {
            $allData[] = [
                    "in_site" => $row["A"],
                    "in_part" => $row["B"],
                    "in_qty_oh" => floatval($row["C"]),
                    "in_date" => $heads["C"],
            ];
        }


        $stock = M("in_mstr");
        $stock->startTrans();
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
                    'in_date' => $data["in_date"]
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
            $warnings[] = $warn;
        }
        
        $stock->commit();
        echo '导入Excel库存成功';

    }    
    
    public function importStocksDetailExcel ($override = false, $type = '')
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
    
    
        $rows = $this->xlsin(self::STOCK_DETAIL_EXCEL_FILE_PATH, 0);
        array_walk_recursive($rows, function(&$val) {
            $val = trim($val);
        });
            // read the first row as header.
            $heads = array_shift($rows);
    
            $allData = [];
            foreach ($rows as $row) {
                $allData[] = [
                        "in_site" => $row["A"],
                        "in_part" => $row["B"],
                        "in_qty_oh" => floatval($row["C"]),
                        "in_date" => $heads["C"],
                        "in_type" => $row["D"]
                ];
            }
            
            dump($allData);
    
            $stock = M("in_mstr_detail");
            $stock->startTrans();
            $stock->lock(true);
            if ($override) {
                $stock->where("1")->delete();
            }
    
            $ptp = D("PtpDet");
            $nonMatchParts = [];
            foreach ($allData as $data) {
                if ($data['in_type'] == 'w') {
                    $data['in_type'] = 'o';
                } else if ($data['in_type'] == 'n') {
                    $data['in_type'] = 'i';
                } else {
                    continue;
                }
                
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
                        'in_date' => $data["in_date"],
                        'in_type' => $data["in_type"]
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
                $warnings[] = $warn;
            }
    
            $stock->commit();
            echo '导入Excel库存成功';
    
    }
    

    
    
    public function importDemandsExcel ($override = false)
    {
        $start = time();
        $override = filter_var($override,FILTER_VALIDATE_BOOLEAN);
    
        $err = false;
        $msg = '';
        try {
            $rows = $this->xlsin("./Public/excelData/demand.xlsx", 0);
            array_walk_recursive($rows, function(&$val) {
                $val = trim($val);
            });
                // read the first row as header.
                $heads = array_shift($rows);
    
                $dmdDateCellMap = [];
                foreach ($heads as $key => $head) {
                    // 将从B列开始的所有列解析为日期
                    if ($key >= "B") {
                        $dmdDateCellMap[$key] = $head;
                    }
                }
    
    
                $allData = [];
                foreach ($rows as $row) {
                    $part = $row["A"];
                    foreach ($dmdDateCellMap as $rowId => $date) {
                        $allData[] = [
                                "dmd_site" => 1000,
                                "dmd_part" => $part,
                                "dmd_date" => $date,
                                "dmd_qty" => floatval($row[$rowId])
                        ];
                    }
    
                }
    
 
    
    
                $ptp = M("ptp_det");
    
                $dmd = M("dmd_det");
                $dmd->startTrans();
    
                if ($override) {
                    $dmd->where("1")->delete();
                }
    
                $unum = $inum = 0;
                $nonMatchParts = [];
                foreach ($allData as $data) {
                    // check part existence and verify part type.
                    $where = $bind = [];
                    $where['ptp_part'] = $data["dmd_part"];
                    $bind[':ptp_part']    =  array($data["dmd_part"],\PDO::PARAM_STR);
                    if ($ptp->where($where)->bind($bind)->count() == 0) {
                        //$ptp->rollback();
                        $nonMatchParts[] = $data["dmd_part"];
                        //continue;
                        //throw new \Exception("导入的生产计划中的零件号: $part 在物料参数表中不存在");
                    }
    
                    $demandCond = [
                            'dmd_site' => $data["dmd_site"],
                            'dmd_part' => $data["dmd_part"],
                            'dmd_date' => $data["dmd_date"],
                    ];
                    if ($dmd->where($demandCond)->count()) {
                        $unum += $dmd->where($demandCond)->save($data);
                    } else {
                        $lid = $dmd->add($data);
                        if ($lid > 0) {
                            $inum++;
                        }
                    }
                }
    
    
                //                 $pmodel = new Model();
                //                 $sql = 'update xy_ptp_det set ptp_ismrp=1,ptp_isdmrp=1,ptp_iswmrp=1,ptp_ismmrp=1;';
                //                 $pmodel->execute($sql);
    
                $dmd->commit();
    
                if ($nonMatchParts) {
                    echo "如下零件号在物料主数据表中不存在： <br />";
                    echo implode(", ", $nonMatchParts);
                }
    
    
    
    
        } catch (\Exception $e) {
    
            $msg = $e->getMessage();
            echo $msg;
        }
    
    
        $end = time();
        $duration = $end - $start;
    
        if ($err) {
            echo "<h1>错误：$msg </h1>";
        } else {
            echo "<h1>导入客户需求成功</h1>";
        }
    }    
    
    protected function convertToStandardDateFormat ($date)
    {
        
    }

    
}