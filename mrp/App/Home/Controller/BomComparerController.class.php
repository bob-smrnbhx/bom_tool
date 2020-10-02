<?php

namespace Home\Controller;
use Think\Controller;


/**
 * convert BOM excel file in smr-nbhx spec to QAD cimload file of 1.4.3 & 1.6 & 13.1 & 13.5 & 14.13.1
 * @author wz
 *
 */
class BomComparerController extends BomParserController
{
    private $_mismatchInOrg = [
            'missing_pars'      => [],
            'missing_relations' => [],
            'error_qty_relations' => [],
            'error_op_relations'  => []
    ];

    
    private $_mismatchInQad = [
            'missing_pars'      => [],
            'missing_relations' => [],
            'error_qty_relations' => [],
            'error_op_relations'  => []
    ];
    
    private $_bomDb;
    
    protected $_cimBomFilePath;
    
    protected function _initialize ()
    {
        import("Org.Util.PHPExcel");
        import("Org.Util.PHPExcel.Writer.Excel5");
        import("Org.Util.PHPExcel.IOFactory.php");
    
        $this->_cimBomFilePath = session("cimBomFile");
        $this->_bomDb = M("ps_mstr");
    }

    
    /**
     * @throws \Exception
     * @return string
     */
    protected function getUploadedFiles ()
    {
        $upload = new \Think\Upload();
        $upload->maxSize   =     30000000 ;
        $upload->exts      =     array('xls', 'xlsx');
        $upload->rootPath  =     './Uploads/cmpBoms/';
        $upload->autoSub = true;
        $upload->subName = array('date','Ymd');
    
        $info = $upload->upload();
        if(!$info) {
            throw new \Exception($upload->getError());
        } else {
            foreach ($info as $file) {
                if ($file["key"] == 'cimBomFile') {
                    $this->_cimBomFilePath = $upload->rootPath . $file['savepath'] . $file["savename"];
                    session("cimBomFile", $this->_cimBomFilePath);
                }
            }
    
            if (is_null($this->_cimBomFilePath)) {
                throw new \Exception("错误：标准格式BOM文件未上传");
            }
        }
    
        return $this;
    }
    
    /**
     * @throws \Exception
     * @return string
     */
    protected function getUploadedFile ()
    {
        $upload = new \Think\Upload();
        $upload->maxSize   =     30000000 ;
        $upload->exts      =     array('xls', 'xlsx');
        $upload->rootPath  =     './Uploads/cmpBoms/';
        $upload->autoSub = true;
        $upload->subName = array('date','Ymd');
    
        $info = $upload->uploadOne($_FILES['cimBomFile']);
        if(!$info) {
            throw new \Exception($upload->getError());
        } else {
            $this->_cimBomFilePath = $upload->rootPath . $info['savepath'] . $info["savename"];
            session("cimBomFile", $this->_cimBomFilePath);
            
            if (is_null($this->_cimBomFilePath)) {
                throw new \Exception("错误：标准格式BOM文件未上传");
            }
        }
    
        return $this;
    }
    
    
    public function test()
    {
        $this->CompareDiff();
    }
    
    public function CompareDiff ()
    {
        $rows = $this->parseExcel($this->_cimBomFilePath, 0);
        $orgBoms = [];
        

        $isFirstLine = true;
        foreach ($rows as $key => $row) {
            // skip the first line as headers
            if ($isFirstLine) {
                $isFirstLine = false;
                continue;
            }
            $orgBoms[$row['A']][$row['B']] = floatval($row['C']);
        }
        
        
        $qadBoms = [];
        foreach ($orgBoms as $par => $orgCompsDetail) {
            $where = $bind = [];
            $where['ps_par'] = ':par';
            $bind[':par']    =  [$par,\PDO::PARAM_STR];
            $result = $this->_bomDb->where($where)->bind($bind)->field(['ps_par','ps_comp','ps_qty_per','ps_site'])->select();
            if ($result === false) {
                throw new \Exception("error occured in fetching bom of par: $par");
            }
            
            if (empty($result)) {
                $this->_mismatchInQad['missing_pars'][$par] = $orgCompsDetail;
                continue;
            } else {
                foreach ($result as $item) {
                    $qadComp = $item["ps_comp"];
                    $qadQtyPer = floatval($item["ps_qty_per"]);
                    $qadBoms[$par][$qadComp] = $qadQtyPer;
                }
            }

            $qadCompsDetail = $qadBoms[$par];

            foreach ($orgCompsDetail as $comp => $qty) {
                if (!isset($qadBoms[$par][$comp])) {
                    $this->_mismatchInQad['missing_relations'][$par][$comp] = $qty;
                    continue;
                }
                
                if ($qty != $qadBoms[$par][$comp]) {
                    $this->_mismatchInQad['error_qty_relations'][$par][$comp] = [
                            'org' => $qty,
                            'cur' => $qadBoms[$par][$comp]
                    ];
                    
                }
            } 
            
            foreach ($qadCompsDetail as $qadComp => $qty) {
                if (!isset($orgBoms[$par][$qadComp])) {
                    $this->_mismatchInOrg['missing_relations'][$par][$qadComp] = $qty;
                }
            }
        }

        
        $this->generateDiffExcel();
    }
    
    protected function generateDiffExcel()
    {
        $this->_objExcel = new \PHPExcel();
        

        //$this->setUseNewSheetForGen(false);
        $this->generateExcelSheet_mismatchedPars('不匹配父级');
        
        $this->setUseNewSheetForGen(true);
        $this->generateExcelSheet_missmatchedRelations('不匹配父子关系');
        
        $this->setUseNewSheetForGen(true);
        $this->generateExcelSheet_errorQty('不匹配用量');
        

//         $this->_objExcel->setActiveSheetIndex(0);
        ob_end_clean();
        $filename = "QADDIFF.xls";
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
        
        $objWriter = \PHPExcel_IOFactory::createWriter($this->_objExcel, 'Excel5');
        $objWriter->save('php://output');
        

    }
    
    
    protected function generateExcelSheet_mismatchedPars ($sheetTitle)
    {
        $objActSheet = $this->_objExcel->getActiveSheet();
        $objActSheet->setTitle($sheetTitle);
        
        
        
        $objActSheet->setCellValue("A1", '父级')->setCellValue("B1", '子级')->setCellValue("C1", '工程原始用量')->setCellValue("D1", 'QAD当前用量')->setCellValue("E1", '不匹配类型');;
        $r = 2;
        foreach ($this->_mismatchInQad['missing_pars'] as $par => $compsDetail) {
            foreach ($compsDetail as $comp => $qty) {
                $objActSheet->setCellValue("A$r", $par)->setCellValue("B$r", $comp)->setCellValue("C$r", $qty)->setCellValue("E$r", "QAD缺失父级");
                $r++;
            }
        }
    }
    
    protected function generateExcelSheet_missmatchedRelations ($sheetTitle)
    {
        $this->_objExcel->createSheet(1);
        $this->_objExcel->setActiveSheetIndex(1);
        $objActSheet = $this->_objExcel->getActiveSheet();
        $objActSheet->setTitle($sheetTitle);
    
        
        $objActSheet->setCellValue("A1", '父级')->setCellValue("B1", '子级')->setCellValue("C1", '工程原始用量')->setCellValue("D1", 'QAD当前用量')->setCellValue("E1", '不匹配类型');
        $r = 2;
        foreach ($this->_mismatchInQad['missing_relations'] as $par => $compsDetail) {
            foreach ($compsDetail as $comp => $qty) {
                $objActSheet->setCellValue("A$r", $par)->setCellValue("B$r", $comp)->setCellValue("C$r", $qty)->setCellValue("E$r", "QAD缺失父子关系");
                $r++;
            }
        }
        foreach ($this->_mismatchInOrg['missing_relations'] as $par => $compsDetail) {
            foreach ($compsDetail as $comp => $qty) {
                $objActSheet->setCellValue("A$r", $par)->setCellValue("B$r", $comp)->setCellValue("D$r", $qty)->setCellValue("E$r", "QAD多出父子关系");
                $r++;
            }
        }
    }
    
    protected function generateExcelSheet_errorQty ($sheetTitle)
    {
        $this->_objExcel->createSheet(2);
        $this->_objExcel->setActiveSheetIndex(2);
        $objActSheet = $this->_objExcel->getActiveSheet();
        $objActSheet->setTitle($sheetTitle);
    
    
        $objActSheet
        ->setCellValue("A1", '父级')->setCellValue("B1", '子级')
        ->setCellValue("C1", '工程原始用量')->setCellValue("D1", 'QAD当前用量')->setCellValue("E1", '不匹配类型');;
        $r = 2;
        foreach ($this->_mismatchInQad['error_qty_relations'] as $par => $compDetail) {
            foreach ($compDetail as $comp => $detail) {
                $objActSheet
                ->setCellValue("A$r", $par)->setCellValue("B$r", $comp)
                ->setCellValue("C$r", $detail['org'])->setCellValue("D$r", $detail['cur'])->setCellValue("E$r", "QAD与原始BOM用量不符");
                $r++;
            }
    
        }
    }
    
 
    public function importSpecBom ()
    {
        $err = false;
        $msg = '';
        try {
            $this->getUploadedFile();
            $msg = "工程bom文件核对完成";
        } catch (\Exception $e) {
            $err = true;
            $msg = "错误：" . $e->getMessage();
        }
    
        $data = new \stdClass();
        $data->statusCode = $err ? 300 : 200;
        $data->err = $err;
        $data->msg = $msg ?  $msg : "未进行任何操作";
        $this->ajaxReturn($data);
    }
    
    public function index ()
    {

        $this->display();
    }
    
    public function exportCmpExcel ()
    {
        set_time_limit(300);
        $this->CompareDiff()->generateDiffExcel();
    }
    


    
    

}