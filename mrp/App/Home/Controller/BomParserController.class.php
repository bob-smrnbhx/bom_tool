<?php

namespace Home\Controller;
use Think\Controller;


/**
 * convert BOM excel file in smr-nbhx spec to QAD cimload file of 1.4.3 & 1.6 & 13.1 & 13.5 & 14.13.1
 * @author wz
 *
 */
class BomParserController extends Controller
{
		const CQ_SUFFIX = '-E';
		const TJ_SUFFIX = '-T';
	
    private $_file;
    private $_orgFilename;
    private $_objExcel;
		private $_useNewSheetForGen = true;
    
    private $_isAltAssyBomFormat = false;
    
		private $_usedSheets = [];
    private $_bomLocs = [];
    private $_genFromZ = false;
    private $_genOaTpl = false;
    private $_allowIgnoreFormat = true;
    
    protected $partsInfo = []; 
    protected $excludedParts = [];
    protected $bomOnlyParts = [];
    
    protected $bomsInfo = [];
    protected $altBomsInfo = [];
	
		protected $routingInfoList = [];
		protected $altRoutingInfoList = [];
    
    
    protected $_mismatchInOrg = [
            'missing_pars'      => [],
            'missing_relations' => [],
            'error_qty_relations' => [],
            'error_op_relations'  => []
    ];
    protected $_mismatchInQad = [
            'missing_pars'      => [],
            'missing_relations' => [],
    ];
    
    protected $_stdBomData;
    protected $_bomDb;
	
	protected function setUseNewSheetForGen ($useNewSheetForGen = true)
	{
		$this->_useNewSheetForGen = (boolean)$useNewSheetForGen;
	}
	
	protected function getActiveSheetForGen ()
	{
		if ($this->_useNewSheetForGen) {
			$this->_objExcel->createSheet();
			$this->_objExcel->setActiveSheetIndex($this->_objExcel->getSheetCount() - 1);
		} 
		
		return $this->_objExcel->getActiveSheet();
	}
    
	/* use later-binding as the excel loading process will take much time to complete  */
	protected function setExcelReader()
	{
		if (empty($this->_objExcel)) {
			$ext = strtolower(pathinfo($this->_file, PATHINFO_EXTENSION));
			if ($ext == 'xls') {
				import("Org.Util.PHPExcel.Reader.Excel5");
				$PHPReader = new \PHPExcel_Reader_Excel5();
			} else if ($ext == 'xlsx') {
				import("Org.Util.PHPExcel.Reader.Excel2007");
				$PHPReader = new \PHPExcel_Reader_Excel2007();
			} else {
				throw new \Exception("unrecognized excel format provided: $ext");
			}
		
			
			// 载入文件
			$this->_objExcel = $PHPReader->load($this->_file);
		}

		
		return $this;
	}
	
	
    protected function parseExcel ($sheetIndex = 0, $maxParsedRow = 0)
    {
        
        // 如果超出工作表最大序号，返回空白数组
        if ($sheetIndex > $this->_objExcel->getSheetCount() - 1) {
            return [];
        }
        
		

        // 获取表中的第一个工作表，如果要获取第二个，把0改为1，依次类推

        $this->_curSheet = $this->_objExcel->getSheet($sheetIndex);
        // 获取总列数
        $allColumn = $this->_curSheet->getHighestColumn();
        $limitColumn = ++$allColumn;
        if ($maxParsedRow) {
            $allRow = $maxParsedRow;
        } else {
            // 获取总行数
            $allRow = $this->_curSheet->getHighestRow();
        }
    
        // 循环获取表中的数据，$currentRow表示当前行，从哪行开始读取数据，索引值从0开始
        for ($currentRow = 1; $currentRow <= $allRow; $currentRow ++) {
            // 从哪列开始，A表示第一列
    
            for ($currentColumn = 'A'; $currentColumn != $limitColumn; $currentColumn ++) {
                // 数据坐标
                $address = $currentColumn . $currentRow;
                // 读取到的数据，保存到数组$arr中
                $arr[$currentRow][$currentColumn] = $this->_curSheet->getCell(
                        $address)->getFormattedValue();
            }
        }
    
        return $arr;
    }
	
	protected function _initialize ()
    {
		import("Org.Util.PHPExcel");
        import("Org.Util.PHPExcel.Writer.Excel5");
        import("Org.Util.PHPExcel.IOFactory.php");
		
        $this->_file = session("peBomFile");
				$this->_usedSheets = session("usedSheets");
        $this->_bomLocs = session("bomLocs");
        $this->_genFromZ = session("genFromZ");
        $this->_genOaTpl = session("genOaTpl");
        $this->_orgFilename = session("orgPeBomFilename");
    }
	
	
    public function setBomFile ($file)
    {
        if (!is_readable($file)) {
            throw new \Exception("Bom file: $file does not exist or can not be read");
        }
        
        $this->_file = $file;
        session("peBomFile", $file);
        
        return $this;
    }
    
	public function setUsedSheet ($usedSheets)
	{
		if (empty($usedSheets)) {
			throw new \Exception("At least one excel sheet should be checked");
		} 
		
		if (!is_array($usedSheets)) {
			$usedSheets = [$usedSheets];
		}
		
		$this->_usedSheets = $usedSheets;
		session("usedSheets", $usedSheets);

		return $this;
	}
		
	
    public function setBomLocs ($locs) 
    {
        if (empty($locs)) {
            $locs = [];
        } else if (!is_array($locs)) {
            $locs = [$locs];
        }
        
        $this->_bomLocs = $locs;
        session("bomLocs", $locs);
        
        return $this;
    }
    
    public function setZConfig ($zConfig)
    {
        $this->_genFromZ = filter_var($zConfig, FILTER_VALIDATE_BOOLEAN);
        
        session("genFromZ", $this->_genFromZ);
        
        return $this;
    }
    
    public function setOaConfig ($oaConfig)
    {
        $this->_genOaTpl = filter_var($oaConfig, FILTER_VALIDATE_BOOLEAN);
        
        session("genOaTpl", $this->_genOaTpl);
        
        return $this;
    }
    
    public function ensureInputFormat ()
    {
		$this->setExcelReader();
        // parse the 2nd sheet
		if (in_array(2, $this->_usedSheets)) {
			$rows = $this->parseExcel(1);
			array_walk_recursive($rows, function(&$val) {
				$val = trim($val);
			});
			

			
			
			// determine the row and start column of parent codes
			for ($r = 1; $r <= 25; $r++) {
				for ($c = 'H'; $c <= 'Z'; $c++) {
					if (isset($rows[$r][$c]) && $rows[$r][$c]) {
						$val = str_replace(" ", "", $rows[$r][$c]);
						if (strpos(strtoupper($val), 'QADCODE') === 0) {
							$parCodeRowNo = $r;
							$startParCodeColNo = ++$c;
							break;
						}
					}
				}
			}
			
			if (!isset($parCodeRowNo)) {
				throw new \Exception("parents QAD CODE in the 1st sheet not found!");
			}
			
			
			
			// determine the column and start row of component codes
			for ($r = 5; $r <= 25; $r++) {
				for ($c = 'A'; $c <= 'G'; $c++) {
					if (isset($rows[$r][$c]) && $rows[$r][$c]) {
						$val = str_replace(" ", "", $rows[$r][$c]);
						if (strpos(strtoupper($val), 'QADCODE') === 0) {
							$startCompCodeRowNo = ++$r;
							$compCodeColNo = $c;
							break;
						}
					}
				}
			}
			
			if (!isset($compCodeColNo)) {
				throw new \Exception("components QAD CODE in the 1st sheet not found!");
			}
		}

		 


        
        // parse the 3rd sheet(if exists) of top-level assy parts info
		if (in_array(3, $this->_usedSheets)) {
			$rows = $this->parseExcel(2);
			if (!empty($rows)) {
				array_walk_recursive($rows, function(&$val) {
					$val = trim($val);
				});
			
				// determine the row and start column of mirror codes
				for ($r = 1; $r <= 20; $r++) {
					for ($c = 'E'; $c <= 'Z'; $c++) {
						if (isset($rows[$r][$c]) && $rows[$r][$c]) {
							$val = str_replace(" ", "", $rows[$r][$c]);
							if (strpos(strtoupper($val), 'QADCODE') === 0) {
								$mirrorCodeRowNo = $r;
								$startMirrorCodeColNo = ++$c;
								break;
							}
						}
					}
				}
		
				if (!isset($mirrorCodeRowNo)) {
					throw new \Exception("mirrors QAD CODE in the 2nd sheet not found!");
				}
		
		

				// determine the column and start row of cover codes
				for ($r = 5; $r <= 20; $r++) {
					for ($c = 'A'; $c <= 'D'; $c++) {
						if (isset($rows[$r][$c]) && $rows[$r][$c]) {
							$val = str_replace(" ", "", $rows[$r][$c]);
							if (strpos(strtoupper($val), 'QADCODE') === 0) {
								$coverCodeColNo = $c;
								$startCoverCodeRowNo = ++$r;
								break;
							}
						}
					}
				}
		
				if (!isset($coverCodeColNo)) {
					throw new \Exception("covers QAD CODE in the 2nd sheet not found!");
				}
			}
		}        
        
        return $this;
    }
    
    public function parse ()
    {
		set_time_limit(900);
		$this->setExcelReader();
        $this->partsInfo = $this->bomsInfo = [];
 
        // parse the 2nd sheet
		if (in_array(2, $this->_usedSheets)) {
			$rows = $this->parseExcel(1);
			array_walk_recursive($rows, function(&$val) {
				$val = trim($val);
			});


			
			// determine the row and start column of parent codes
			for ($r = 1; $r <= 25; $r++) {
				for ($c = 'H'; $c <= 'Z'; $c++) {
					if (isset($rows[$r][$c]) && $rows[$r][$c]) {
						$val = str_replace(" ", "", $rows[$r][$c]);
						if (strpos(strtoupper($val), 'QADCODE') === 0) {
							$parCodeRowNo = $r;
							// continue to find desc1 desc2 custcode start cells positions in the same column
							for ($r = 1; $r < 25; $r++) {
							    if (isset($rows[$r][$c]) && $rows[$r][$c]) {
							        $val = preg_replace('/\s+/', '' , $rows[$r][$c]); 
							        if (strpos(strtoupper($val), 'PRODUCTNAME') === 0) {
							            $parDesc1RowNo = $r + 1; // skip the desc1 group row
							        }

							        if (strpos(strtoupper($val), 'SMRPARTNUMBER') === 0) {
							            $parDesc2RowNo = $r;
							        }

							        if (strpos(strtoupper($val), 'CUSTOMERPARTNUMBER') === 0) {
							            $parCustRowNo = $r;
							        }
							    }
							}
							
							$startParCodeColNo = ++$c;
							break;
						}
					}
				}
			}
			
			if (!isset($parCodeRowNo)) {
				throw new \Exception("parents QAD CODE in the 1st sheet not found!");
			}
			


			// parse mirror&cover items info
			$c = $startParCodeColNo;
			$parsInfo = [];
			while (isset($rows[$parCodeRowNo][$c])) {
			   $r = $parCodeRowNo;
			   $code = $rows[$r][$c];
			   
			   if (self::isValidMaterialCode($code) || self::isPlaceHolderMaterialCode($code)) {
				   $desc1 = self::getMainNameOfDesc($rows[$parDesc1RowNo][$c]);
				   $desc2 = self::getMainNameOfDesc($rows[$parDesc2RowNo][$c]);
				   $custCode = self::getMainNameOfDesc($rows[$parCustRowNo][$c]);
				   if (empty($desc2)) {
					   $desc2 = $custCode;
				   }

				   $parsInfo[$c] = [
						   "code"  => $code,
						   "desc1" => $desc1,
						   "desc2" => $desc2,
						   "custCode" => $custCode,
						   "type"  => "M",
						   "unit"  => 'EA'
				   ];
				   $this->partsInfo[$code] = $parsInfo[$c];
				   
				   if ($this->_allowIgnoreFormat) {
				       // if the part code is marked as being created already (with bold-weight font), then add it in exclusive list
				       $ignored = $this->_curSheet->getStyle("$c$r")->getFont()->getBold();
				       if ($ignored) {
				           $this->excludedParts[$code] = $code;
				       }
				       //$onlyBomNeeded = $this->_curSheet->getStyle("$c$r")->getFont()->getColor()->getARGB() == \PHPExcel_Style_Color::COLOR_RED;
				       $onlyBomNeeded = $this->_curSheet->getStyle("$c$r")->getFont()->getItalic();
				       if ($onlyBomNeeded) {
				           $this->bomOnlyParts[$code] = $code;
				       }
				   }
			   }

			   $c++;
			}


			// determine the column and start row of component codes
			for ($r = 5; $r <= 20; $r++) {
				for ($c = 'A'; $c <= 'Z'; $c++) {
					if (isset($rows[$r][$c]) && $rows[$r][$c]) {
						$val = str_replace(" ", "", $rows[$r][$c]);
						if (strpos(strtoupper($val), 'QADCODE') === 0) {
						    $compCodeColNo = $c;
							
							// continue to find other info cells positions in the same row
							for ($c = 'A'; $c <= 'Z'; $c++) {
							    if (isset($rows[$r][$c]) && $rows[$r][$c]) {
							        $val = preg_replace('/\s+/', '' , $rows[$r][$c]); 
							        if (strpos(strtoupper($val), 'PARTNAME') === 0) {
							            $compDesc1ColNo = $c; 
							        }
							        
							        if (strpos(strtoupper($val), 'PROJECTNAME') === 0) {
							            $compPromoColNo = $c;
							        }
							        
							        if (strpos(strtoupper($val), 'LH/RH') === 0) {
							            $compDirColNo = $c;
							        }
							        
							        if (strpos(strtoupper($val), 'SMRPARTNO') === 0) {
							            $compDesc2ColNo = $c;
							        }
							        
							        if (strpos(strtoupper($val), 'UNIT') === 0) {
							            $compUnitColNo = $c;
							        }
							        
							        if (strpos(strtoupper($val), 'M/B') === 0) {
							            $compTypeColNo = $c;
							        }
							        
							        if (strpos(strtoupper($val), 'SUPPLIER') === 0) {
							            $compSupplierColNo = $c;
							        }
									
									if (strpos(strtoupper($val), 'SUPPLIERCODE') === 0) {
							            $compSupplierCodeColNo = $c;
							        }
							        
							        if (strpos(strtoupper($val), 'LH/RH') === 0) {
							            $compDirColNo = $c;
							        }
							        
							        if (strpos(strtoupper($val), 'MATERIALCODE') === 0) {
							            $compMatCodeColNo = $c;
							        }
							        
							        if (strpos(strtoupper($val), 'WEIGHT(KG)') === 0) {
							            $compMatPerColNo = $c;
							        }
							    }
							}
							
							$startCompCodeRowNo = ++$r;
							break;
						}
					}
				}
			}
			
			

			if (!isset($compCodeColNo)) {
				throw new \Exception("components QAD CODE in the 1st sheet not found!");
			}
			
			// parse component items info
			$r = $startCompCodeRowNo;
			$compsInfo = [];
			while (isset($rows[$r])) {
				$c = $compCodeColNo;
				$code = $rows[$r][$c];
				if (!empty($code) && self::isValidMaterialCode($code)) {
					$orgDesc1 = self::getMainNameOfDesc($rows[$r][$compDesc1ColNo]);
					$promo = $rows[$r][$compPromoColNo];
					$dir = $rows[$r][$compDirColNo];
					$desc1 = self::composeFullDesc($orgDesc1, $promo, $dir);
					
					$desc2 = self::getMainNameOfDesc($rows[$r][$compDesc2ColNo]);
					$type  = strtoupper($rows[$r][$compTypeColNo]) == 'MAKE' ? "M" : "B";
					$unit  = strtoupper($rows[$r][$compUnitColNo]);
					$supplier = $rows[$r][$compSupplierColNo];
					$supplierCode = $rows[$r][$compSupplierCodeColNo];
					$matCode = $rows[$r][$compMatCodeColNo];
					$matPer  = $rows[$r][$compMatPerColNo];
					
					$compsInfo[$r] = [
							"code"  => $code,
							"desc1" => $desc1,
							"desc2" => $desc2,
							"type"  => $type,
							"unit"  => $unit
					];
					if ($type == "M" && $matCode && $matPer) {
						$compsInfo[$r] += [
								"matCode" => $matCode,
								"matPer"  => $matPer
						];
					}
					if ($type == "B") {
						$compsInfo[$r]["supplier"] = $supplier;
						$compsInfo[$r]["supCode"] = $supplierCode;
					}
					
					if (!isset($this->partsInfo[$code])) {
						// parents info would be more detailed than comps info
						$this->partsInfo[$code] = $compsInfo[$r];
					}
					
					
					if ($this->_allowIgnoreFormat) {
					    // if the part code is marked as being created already (with bold-weight font), then add it in exclusive list
					    $ignored = $this->_curSheet->getStyle("$c$r")->getFont()->getBold();
					    if ($ignored) {
					        $this->excludedParts[$code] = $code;
					    }
					    //$onlyBomNeeded = $this->_curSheet->getStyle("$c$r")->getFont()->getColor()->getARGB() == \PHPExcel_Style_Color::COLOR_RED;
					    $onlyBomNeeded = $this->_curSheet->getStyle("$c$r")->getFont()->getItalic();
					    if ($onlyBomNeeded) {
					        $this->bomOnlyParts[$code] = $code;
					    }
					}
				
				}

				$r++;
			}
			

			
			// parse mirror&cover boms
			foreach ($parsInfo as $c => $parInfo) {
				$parCode = $parInfo["code"];
				foreach ($compsInfo as $r => $compInfo) {
					$compCode = $compInfo["code"];
					if ($rows[$r][$c] && is_numeric($rows[$r][$c])) {
						$per = floatval($rows[$r][$c]);
						$op = self::getOpByParCode($parCode);
						$this->bomsInfo[$parCode][] = [$compCode, $per, $op];
					}
				}
			}
			
			
			// parse low-component boms, while material code and per column may contain multiple ones 
			foreach ($compsInfo as $r => $compInfo) {
				if ($compInfo["type"] == 'M' && isset($compInfo["matCode"])) {
					$compCode = $compInfo["code"];
					$op = self::getOpByParCode($compCode);
					$matCodes = explode('/', $compInfo["matCode"]);
					$matPers = explode('/', $compInfo["matPer"]);
					// deal with slash(/) separated material codes and pers
					foreach ($matCodes as $no => $matCode) {
						$matCode = trim($matCode);
						$per = floatval($matPers[$no]);
						$this->bomsInfo[$compCode][] = [$matCode, $per, $op];
					}
				}
			}			
		}
        



        // parse the 3rd sheet(if exists) of top-level assy parts info
		if (in_array(3, $this->_usedSheets)) {
			$rows = $this->parseExcel(2);
			if (!empty($rows)) {
				array_walk_recursive($rows, function(&$val) {
					$val = trim($val);
				});
				
				// determine the row and start column of mirror codes
				for ($r = 1; $r <= 25; $r++) {
					for ($c = 'E'; $c <= 'Z'; $c++) {
						if (isset($rows[$r][$c]) && $rows[$r][$c]) {
							$val = preg_replace('/\s+/', '' , $rows[$r][$c]); 
							if (strpos(strtoupper($val), 'QADCODE') === 0) {
								$mirrorCodeRowNo = $r;
								
								// find other info cells position in the same row
								for ($r = 1; $r <= 25; $r++) {
								    if (isset($rows[$r][$c]) && $rows[$r][$c]) {
								        $val = preg_replace('/\s+/', '' , $rows[$r][$c]);
								        if (strpos(strtoupper($val), 'PRODUCTNAME') === 0) {
								            $mirrorDescRowNo = $r + 1; // skip the desc group header
								        }
								    }
								}
								
								$startMirrorCodeColNo = ++$c;
								break;
							}
						}
					}
				}
				
				if (!isset($mirrorCodeRowNo)) {
					throw new \Exception("mirrors QAD CODE in the 2nd sheet not found!");
				}
				
				
				// determine the column and start row of cover codes
				for ($r = 5; $r <= 25; $r++) {
					for ($c = 'A'; $c <= 'D'; $c++) {
						if (isset($rows[$r][$c]) && $rows[$r][$c]) {
							$val = preg_replace('/\s+/', '' , $rows[$r][$c]); 
							if (strpos(strtoupper($val), 'QADCODE') === 0) {
								$coverCodeColNo = $c;
								
								for ($c = 'A'; $c <= 'Z'; $c++) {
								    if (isset($rows[$r][$c]) && $rows[$r][$c]) {
								        $val = preg_replace('/\s+/', '' , $rows[$r][$c]);
								        if (strpos(strtoupper($val), 'PAINTINGPARTNAME') === 0) {
								            $coverDescColNo = $c;
								        }
								        
										if (strpos(strtoupper($val), 'PROJECTNAME') === 0) {
								            $coverProjColNo = $c;
								        }
										
								        if (strpos(strtoupper($val), 'LH/RH') === 0) {
								            $coverDirColNo = $c;
								        }
								    }
								}
								
								$startCoverCodeRowNo = ++$r;
								break;
							}
						}
					}
				}
				

				
				if (!isset($coverCodeColNo)) {
					throw new \Exception("covers QAD CODE in the 2nd sheet not found!");
				}
				
				 
				$r = $startCoverCodeRowNo;
				$assysInfo = [];
				while (isset($rows[$r])) {
					if (self::isValidMaterialCode($rows[$r][$coverCodeColNo])) {
					    if ($r == $startCoverCodeRowNo || !self::isValidMaterialCode($rows[$r - 1][$coverCodeColNo])) {
					        // if prior line is blank or invalid line or the first line, then treat as the start of new covers group, and find the related single mirror part info
					        $curGroupParCodes = [];
					        $c = $startMirrorCodeColNo;
					        while (isset($rows[$r][$c])) {
					            $mirrorCode = $rows[$mirrorCodeRowNo][$c];
					            if (!empty($mirrorCode) && (self::isValidMaterialCode($mirrorCode) || self::isPlaceHolderMaterialCode($mirrorCode))) {
					                $mirrorDesc1 = self::getMainNameOfDesc($rows[$mirrorDescRowNo][$c]);
					                if ($rows[$r][$c]) {
					                    // use the first cell as assy desc2&custCode and second cell as assy code among the joint vertical cells
					                    $desc2 = self::getMainNameOfDesc($rows[$r][$c]);
					                    $code = $rows[$r + 1][$c];
					                    if (!empty($code) && self::isValidMaterialCode($code)) {
					                        $assyMainDesc = str_replace("镜体", "", $mirrorDesc1);
											$assyMainDesc = str_replace("左", "", $assyMainDesc);
											$assyMainDesc = str_replace("右", "", $assyMainDesc);
					                        	
					                        $this->partsInfo[$code] = [
					                                "code"  => $code,
					                                "desc2" => $desc2,
					                                "mainDesc1" => $assyMainDesc,
					                                "custCode" => $desc2,
					                                "type"  => "M",
					                                "unit"  => 'EA',
					                        ];
					                        
					                        if (self::isPlaceHolderMaterialCode($mirrorCode)) {
					                            if (isset($this->bomsInfo[$mirrorCode])) {
					                                $this->bomsInfo[$code] = $this->bomsInfo[$mirrorCode];
					                            }
					                        } else {
					                            $this->bomsInfo[$code][] = [$mirrorCode, 1];
					                        }
											
											$op = self::getOpByParCode($code);
											if (!empty($op)) {
												foreach ($this->bomsInfo[$code] as $key => $bomInfo) {
													$this->bomsInfo[$code][$key][2] = $op;
												}
											}
					                        
					                        $curGroupParCodes[] = $code;
					                        
					                        if ($this->_allowIgnoreFormat) {
					                            // if the part code is marked as being created already (with bold-weight font), then add it in exclusive list
					                            $codeRow = $r + 1;
					                            $codeCol = $c;
					                            $ignored = $this->_curSheet->getStyle("$codeCol$codeRow")->getFont()->getBold();
					                            if ($ignored) {
					                                $this->excludedParts[$code] = $code;
					                            }
					                            //$onlyBomNeeded = $this->_curSheet->getStyle("$codeCol$codeRow")->getFont()->getColor()->getARGB() == \PHPExcel_Style_Color::COLOR_RED;
					                            $onlyBomNeeded = $this->_curSheet->getStyle("$codeCol$codeRow")->getFont()->getItalic();
					                            if ($onlyBomNeeded) {
					                                $this->bomOnlyParts[$code] = $code;
					                            }
					                        }
					                    }
					                    	
					                }
					            }
					            $c++;
					        }
					    }
					    
					    // add the cover code of current line to the bom of the current assy codes in the same group.
					    foreach ($curGroupParCodes as $code) {
					        $coverCode = $rows[$r][$coverCodeColNo];
					        $this->bomsInfo[$code][] = [$coverCode, 1];
					        	
					        // add the color suffix to the assy desc1 on the base of mirror desc1 and color of decoration cover desc1.
					        if (!isset($this->partsInfo[$code]['desc1'])) {
					            $coverDesc1 = self::getMainNameOfDesc($rows[$r][$coverDescColNo]);
					            if (preg_match('/装饰罩(.+)\s*(左|右)?/', $coverDesc1, $match) or preg_match('/油漆小总成(.+)\s*(左|右)?/', $coverDesc1, $match)) {
									$coverProj = trim($rows[$r][$coverProjColNo]);
									$coverDir = trim($rows[$r][$coverDirColNo]); 
					                $coverColor = trim($match[1]);
					                $this->partsInfo[$code]['desc1'] = $this->partsInfo[$code]['mainDesc1'] . " $coverColor " . $match[2];
									$this->partsInfo[$code]['desc1'] = self::composeFullDesc($this->partsInfo[$code]['desc1'], $coverProj, $coverDir);
					            }
					        }
					    }
					}
                    
					$r++;
				}
			}			
		}
        
 
        foreach ($this->partsInfo as $code => $partInfo) {
            // added fixed error cover sign to the assy with no color parsed
            if (!isset($this->partsInfo[$code]["desc1"])) {
                $this->partsInfo[$code]["desc1"] = $this->partsInfo[$code]["mainDesc1"] . " 颜色未知……";
            }
            
            if (self::isPlaceHolderMaterialCode($code) || isset($this->excludedParts[$code]) || isset($this->bomOnlyParts[$code])) {
				// clear placeholder codes and excluded codes
                unset($this->partsInfo[$code]);
            } 
			/*else if ($this->_genFromZ && self::isDashZMaterialCode($code)) {
				// add normal part from z-code part if needed
				$normalCode = substr($code, 0, -2);
                $partInfo["code"] = $normalCode;
				$this->partsInfo[$normalCode] = $partInfo;
			}*/
        }
		
		
        foreach ($this->bomsInfo as $code => $bomInfo) {
            if (self::isPlaceHolderMaterialCode($code) || isset($this->excludedParts[$code])) {
				// clear placeholder codes and excluded codes
                unset($this->bomsInfo[$code]);
            } 
			// the -z convertion process is now deprecated by finance
			/*else if (self::isDashZMaterialCode($code)) {
				$normalCode = substr($code, 0, -2);
                $altCode = "$normalCode-D";
                
				// add alt bom info(-D related)
                $this->altBomsInfo[$altCode] = [
                        [$code, 1, 20]
                ];
				
				if ($this->_genFromZ) {
					// add normal bom from z-code par bom if needed
					$this->bomsInfo[$normalCode] = $bomInfo;
				}
			}*/
        }
        
		$addSubsidiaryBom = in_array("CQ", $this->_bomLocs) || in_array("TJ", $this->_bomLocs);
		
		foreach($this->bomsInfo as $par => $bomInfo) {
			$op = reset($bomInfo)[2];
			$this->routingInfoList[] = [$par, $op];
			if ($op == 10) {
				if (!$addSubsidiaryBom) {
					$this->routingInfoList[] = [$par, 9];
				} else {
					$this->routingInfoList[] = [$par, 8];
				}
			} else if ($op == 20) {
				if (!$addSubsidiaryBom) {
					//$this->routingInfoList[] = [$par, 18];
				} else {
					$this->routingInfoList[] = [$par, 16];
				}
			}
		}
		
      foreach($this->altBomsInfo as $par => $bomInfo) {
			$op = reset($bomInfo)[2];
			$this->altRoutingInfoList[] = [$par, $op];
			if ($op == 10) {
				if (!$addSubsidiaryBom) {
					$this->altRoutingInfoList[] = [$par, 9];
				} else {
					$this->altRoutingInfoList[] = [$par, 8];
				}
			} else if ($op == 20) {
				if (!$addSubsidiaryBom) {
					//$this->altRoutingInfoList[] = [$par, 18];
				} else {
					$this->altRoutingInfoList[] = [$par, 16];
				}
			}
		}
		

        
        //ksort($this->partsInfo);
        //ksort($this->bomsInfo);
        
        return $this;
    }
    

    protected function  generateStdExcel()
    {
        $this->_objExcel = new \PHPExcel();
    
        $this->setUseNewSheetForGen(false);
        if ($this->_bomLocs) {
            if (in_array("NB", $this->_bomLocs)) {
                $this->generateExcelSheet_stdBom('标准BOM', false);
            }
            if (in_array("CQ", $this->_bomLocs)) {
                $this->generateExcelSheet_stdBom('标准BOM', true);
            }
        }
    
        $this->_objExcel->setActiveSheetIndex(0);
        ob_end_clean();
        $filename = "QADCIM(" . pathinfo($this->_orgFilename, PATHINFO_FILENAME) . ').xls';
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
    
        $objWriter = \PHPExcel_IOFactory::createWriter($this->_objExcel, 'Excel5');
        $objWriter->save('php://output');
    }
	
	
 
	
    
    protected function generateCimFormatExcel ()
    {   

				$this->_objExcel = new \PHPExcel();
				
		        // 1.4.3
				$this->setUseNewSheetForGen(false);
		    $this->generateExcelSheet_1_4_3('1.4.3');
				$this->setUseNewSheetForGen(true);
        

 
		
        // 1.16 
        $this->generateExcelSheet_1_6("1.16");

        // 13.1
        if ($this->_bomLocs) {
            if (in_array("NB", $this->_bomLocs) && $this->altBomsInfo) {
                $this->generateExcelSheet_13_1_Alt("13.1-NB");
            }
            if (in_array("CQ", $this->_bomLocs)) {
                $this->generateExcelSheet_13_1("13.1-CQ", self::CQ_SUFFIX);
            }
            if (in_array("TJ", $this->_bomLocs)) {
                $this->generateExcelSheet_13_1("13.1-TJ", self::TJ_SUFFIX);
            }
        }
        
        
        // 13.5 
        if ($this->_bomLocs) {
            if (in_array("NB", $this->_bomLocs)) {
                $this->generateExcelSheet_13_5("13.5-NB");
            }
            if (in_array("CQ", $this->_bomLocs)) {
                $this->generateExcelSheet_13_5("13.5-CQ", self::CQ_SUFFIX);
            }
            if (in_array("TJ", $this->_bomLocs)) {
                $this->generateExcelSheet_13_5("13.5-TJ", self::TJ_SUFFIX);
            }
        }

        // 13.15
        if ($this->altBomsInfo) {
            $this->generateExcelSheet_13_15_Alt("13.15-NB", false);
        }
        
        // 14.13.1
        if ($this->_bomLocs) {
            if (in_array("NB", $this->_bomLocs)) {
                $this->generateExcelSheet_14_13_1("14.13.1-NB");
            }
            if (in_array("CQ", $this->_bomLocs)) {
                $this->generateExcelSheet_14_13_1("14.13.1-CQ", self::CQ_SUFFIX);
            }
            if (in_array("TJ", $this->_bomLocs)) {
                $this->generateExcelSheet_14_13_1("14.13.1-TJ", self::TJ_SUFFIX);
            }
        }
       
        // 14.15.1
        if ($this->altBomsInfo) {
            $this->generateExcelSheet_14_15_1_Alt("14.15.1-NB");
        }
        
				$this->generateExcelSheet_7_3_13("7.3.13-新增寄售");
        
        // oa tpl
        if ($this->_genOaTpl) {
            $this->generateExcelSheet_Oa_Circulation("OA流转单-自制", "M");
            $this->generateExcelSheet_Oa_Circulation("OA流转单-外购", "B");
        }
        
        $this->_objExcel->setActiveSheetIndex(0);
        ob_end_clean();
        $filename = "QADCIM(" . iconv('utf-8', 'gbk', pathinfo($this->_orgFilename, PATHINFO_FILENAME)) . ').xls';
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename=\"$filename\"");
        header('Cache-Control: max-age=0');
        
        $objWriter = \PHPExcel_IOFactory::createWriter($this->_objExcel, 'Excel5');
        $objWriter->save('php://output');
        
    }
    
    
    protected function generateExcelSheet_1_4_3 ($sheetTitle)
    {
        $objActSheet = $this->getActiveSheetForGen();
        $objActSheet->setTitle($sheetTitle);
        
        $objActSheet
        ->setCellValue('A1', 'ppptmt04.p')
        ->setCellValue("A2", "Item Number")->setCellValue("B2", "UM")->setCellValue("C2", "desc1")
        ->setCellValue("D2", "desc2")->setCellValue("E2", "Product line")->setCellValue("F2", "added date")
        ->setCellValue("G2", "design group")->setCellValue("H2", "Promotion group")->setCellValue("I2", "Part type")
        ->setCellValue("J2", "status")->setCellValue("K2", "esc")
        ->setCellValue("A3", "物料代码")->setCellValue("B3", "计量单位")->setCellValue("C3", "描述1")
        ->setCellValue("D3", "描述2")->setCellValue("E3", "产品线")->setCellValue("F3", "加数")
        ->setCellValue("H3", "项目组")->setCellValue("I3", "物料类型")
        ->setCellValue("J3", "状态")->setCellValue("K3", "esc")
        ->setCellValue("A5", "1/1-C")->setCellValue("B5", "2/1-C")->setCellValue("C5", "2/2-C")
        ->setCellValue("D5", "2/3-C")->setCellValue("E5", "3/1-C")->setCellValue("F5", "3/2-C")
        ->setCellValue("G5", "3/3-C")->setCellValue("H5", "3/4-C")->setCellValue("I5", "3/5-C")
        ->setCellValue("J5", "3/6-C")->setCellValue("K5", "4/1-C");
        ;
        
        
        $r = 6;
//         $pValue = new \PHPExcel_Style_Conditional();
//         $pValue
//         ->setConditionType(\PHPExcel_Style_Conditional::CONDITION_EXPRESSION)
//         //->setOperatorType(\PHPExcel_Style_Conditional::OPERATOR_EQUAL)
//         ->addCondition("=LENB(C1)>24")
//         ->getStyle()->getFont()->getColor()->setRGB('FF0000');;
//         $objActSheet->setConditionalStyles("C9", [$pValue]);

        foreach ($this->partsInfo as $partInfo) {
            // highlight overlength desc cells
            if (self::isDescOverlength($partInfo["desc1"])) {
                $objActSheet->getStyle("C$r")->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
            }
            if (self::isCustCodeOverlength($partInfo["desc2"])) {
                $objActSheet->getStyle("D$r")->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
            }
            
            
            $objActSheet
            ->setCellValue("A$r", $partInfo["code"])
            ->setCellValue("B$r", $partInfo["unit"])
            ->setCellValue("C$r", $partInfo["desc1"])
            ->setCellValue("D$r", $partInfo["desc2"])
            ->setCellValue("F$r", '-')->setCellValue("G$r", '-')
            ->setCellValue("J$r", "-")->setCellValue("K$r", ".")
            ;
            $r++;
        }
        

        // highlight required user input fields
        foreach (["E", "H", "I"] as $j) {
            for ($i = 6; $i < $r; $i++) {
                $objActSheet->getStyle("$j$i")->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            }
        }
        

        for ($c = 'A'; $c < 'Z'; $c++) {
            $objActSheet->getColumnDimension($c)->setAutoSize(true);
        }

    }
    
    protected function generateExcelSheet_1_6 ($sheetTitle)
    {
		$objActSheet = $this->getActiveSheetForGen();
        $objActSheet->setTitle($sheetTitle);
        
        $objActSheet
        ->setCellValue("A1", "ppcpmt.p")
        ->setCellValue("A2", "Customer/Ship-To")->setCellValue("B2", "Customer Item")->setCellValue("C2", "Item Number")
        ->setCellValue("D2", "Comment")->setCellValue("E2", "Display Customer Item")->setCellValue("F2", "Customer Item ECO Nbr")
        ->setCellValue("G2", "esc")
        ->setCellValue("A3", "客户/货物发往")->setCellValue("B3", "客户物料号")->setCellValue("C3", "物料代码")
        ->setCellValue("D3", "说明")->setCellValue("E3", "显示客户物料")->setCellValue("F3", "工程变更单号")
        ->setCellValue("G3", "esc")
        ->setCellValue("A5", "1/1-C")->setCellValue("B5", "1/2-C")->setCellValue("C5", "2/1-C")
        ->setCellValue("D5", "2/2-C")->setCellValue("E5", "2/3-C")->setCellValue("F5", "2/4-C")
        ->setCellValue("G5", "3/1-C")
        ;
        

        $r = 6;
        foreach ($this->partsInfo as $partInfo) {
            if (!isset($partInfo["custCode"]) || empty($partInfo["custCode"]) 
			// || self::isDashZMaterialCode($partInfo["code"])
			) {
                continue;
            }
            
            // highlight overlength cells
            if (self::isCustCodeOverlength($partInfo["custCode"])) {
                $objActSheet->getStyle("B$r")->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
            }
        
            $objActSheet
            ->setCellValue("B$r", $partInfo["custCode"])
            ->setCellValue("C$r", $partInfo["code"])
            ->setCellValue("D$r", $partInfo["desc1"])
            ->setCellValue("E$r", $partInfo["custCode"])
            ->setCellValue("F$r", '-')
            ->setCellValue("G$r", ".")
            ;
            $r++;
        }
        

        // highlight required user input fields
        foreach (["A"] as $j) {
            for ($i = 6; $i < $r; $i++) {
                $objActSheet->getStyle("$j$i")->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            }
        }
        


        for ($c = 'A'; $c < 'Z'; $c++) {
            $objActSheet->getColumnDimension($c)->setAutoSize(true);
        }

    }
    
    protected function generateExcelSheet_13_1 ($sheetTitle, $bomSuffix = '')
    {
		$objActSheet = $this->getActiveSheetForGen();
        $objActSheet->setTitle($sheetTitle);
        
        $objActSheet
        ->setCellValue("A1", "bmmamt.p")
        ->setCellValue("A2", "BOM Code")->setCellValue("B2", "Description")->setCellValue("C2", "unit")->setCellValue("D2", "esc")
        ->setCellValue("A3", "BOM代码")->setCellValue("B3", "物料描述")->setCellValue("C3", "单位")->setCellValue("D3", "esc")
        ->setCellValue("A5", "1/1-C")->setCellValue("B5", "2/1-C")->setCellValue("C5", "2/2-C")->setCellValue("D5", "3/1-C")
        ;
        
        $r = 6;
        foreach ($this->bomsInfo as $par => $bomInfo) {
            if ($bomSuffix && self::isDashZMaterialCode($par)) {
                continue;
            }
            // highlight overlength desc cells
            if (self::isDescOverlength($this->partsInfo[$par]["desc1"])) {
                $objActSheet->getStyle("B$r")->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
            }
            
            $code = $par.$bomSuffix;            
            
            $objActSheet
            ->setCellValue("A$r", $code)
            ->setCellValue("B$r", $this->partsInfo[$par]["desc1"])
            ->setCellValue("C$r", $this->partsInfo[$par]["unit"])
            ->setCellValue("D$r", ".")
            ;
            $r++;
        }
        
        for ($c = 'A'; $c < 'Z'; $c++) {
            $objActSheet->getColumnDimension($c)->setAutoSize(true);
        }

    }
    
    protected function generateExcelSheet_13_1_Alt ($sheetTitle)
    {
		$objActSheet = $this->getActiveSheetForGen();
        $objActSheet->setTitle($sheetTitle);
    
        $objActSheet
        ->setCellValue("A1", "bmmamt.p")
        ->setCellValue("A2", "BOM Code")->setCellValue("B2", "Description")->setCellValue("C2", "unit")->setCellValue("D2", "esc")
        ->setCellValue("A3", "BOM代码")->setCellValue("B3", "物料描述")->setCellValue("C3", "单位")->setCellValue("D3", "esc")
        ->setCellValue("A5", "1/1-C")->setCellValue("B5", "2/1-C")->setCellValue("C5", "2/2-C")->setCellValue("D5", "3/1-C")
        ;
    
        $r = 6;    
        foreach ($this->altBomsInfo as $par => $bomInfo) {
            // highlight overlength desc cells
            if (self::isDescOverlength($this->partsInfo[$par]["desc1"])) {
                $objActSheet->getStyle("B$r")->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
            }
    
            $zCode = substr($par, 0, -2) . '-Z';
    
            $objActSheet
            ->setCellValue("A$r", $par)
            ->setCellValue("B$r", $this->partsInfo[$zCode]["desc1"])
            ->setCellValue("C$r", $this->partsInfo[$zCode]["unit"])
            ->setCellValue("D$r", ".")
            ;
            $r++;
        }
    
    
        for ($c = 'A'; $c < 'Z'; $c++) {
            $objActSheet->getColumnDimension($c)->setAutoSize(true);
        }
    
    }
    
    protected function generateExcelSheet_13_5 ($sheetTitle, $bomSuffix = '')
    {
				$objActSheet = $this->getActiveSheetForGen();
        $objActSheet->setTitle($sheetTitle);
        
        $objActSheet
        ->setCellValue("A1", "bmpsmt.p")
        ->setCellValue("A2", "Parent Item")->setCellValue("B2", "Component Item")->setCellValue("C2", "quantity")
        ->setCellValue("D2", "type")->setCellValue("E2", "start")->setCellValue("F2", "end")
        ->setCellValue("G2", "remark")->setCellValue("H2", "scrap")->setCellValue("I2", "LTO")
        ->setCellValue("J2", "operation")->setCellValue("K2", "esc")
        ->setCellValue("A3", "父级代码")->setCellValue("B3", "子级代码")->setCellValue("C3", "数量")
        ->setCellValue("D3", "类型")->setCellValue("E3", "开始日期")->setCellValue("F3", "结束日期")
        ->setCellValue("G3", "说明")->setCellValue("H3", "废品率")->setCellValue("I3", "偏移提前天数")
        ->setCellValue("J3", "工序")->setCellValue("K3", "esc")
        ->setCellValue("A5", "1/1-C")->setCellValue("B5", "2/1-C")->setCellValue("C5", "3/1-C")
        ->setCellValue("D5", "3/2-C")->setCellValue("E5", "3/3-C")->setCellValue("F5", "3/4-C")
        ->setCellValue("G5", "3/5-C")->setCellValue("H5", "3/6-C")->setCellValue("I5", "3/7-C")
        ->setCellValue("J5", "3/8-C")->setCellValue("K5", "4/1-C");
        
        $r = 6;
        foreach ($this->bomsInfo as $par => $compsInfo) {
            if ($bomSuffix) {
                if (self::isDashZMaterialCode($par)) {
                    continue;
                }
                
                $par = $par.$bomSuffix; 
            }
            
            foreach ($compsInfo as $compInfo)  {
                $comp = $compInfo[0];
                $per = $compInfo[1];
                $op = $compInfo[2];
                
                // if ($this->partsInfo[$comp]["type"] == 'M' && $bomSuffix) {
                    // $comp = "$comp-E";
                // }
                
                $objActSheet->setCellValue("A$r", $par)->setCellValue("B$r", $comp)->setCellValue("C$r", $per);
                for ($c = 'D'; $c <= 'I'; $c++) {
                    $objActSheet->setCellValue("$c$r", "-");
                }
                $objActSheet->setCellValue("J$r", $op);
                if (empty($op)) {
                    // highlight empty op cells (due to uncertainty by part code)
                    $objActSheet->getStyle("J$r")->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                }
                $objActSheet->setCellValue("K$r", ".");
                $r++;
            }
        }
        
        if (!$bomSuffix && $this->altBomsInfo) {
            foreach ($this->altBomsInfo as $par => $compsInfo) {
                foreach ($compsInfo as $compInfo)  {
                    $comp = $compInfo[0];
                    $per = $compInfo[1];
                    $op = $compInfo[2];
            
                    $objActSheet->setCellValue("A$r", $par)->setCellValue("B$r", $comp)->setCellValue("C$r", $per);
                    for ($c = 'D'; $c <= 'I'; $c++) {
                        $objActSheet->setCellValue("$c$r", "-");
                    }
                    $objActSheet->setCellValue("J$r", $op);
                    if (empty($op)) {
                        // highlight empty op cells (due to uncertainty by part code)
                        $objActSheet->getStyle("J$r")->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                    }
                    $objActSheet->setCellValue("K$r", ".");
                    $r++;
                }
            }
        }
        
        
        for ($c = 'A'; $c < 'Z'; $c++) {
            $objActSheet->getColumnDimension($c)->setAutoSize(true);
        }
    }
    
    protected function generateExcelSheet_stdBom ($sheetTitle, $bomSuffix = '')
    {
        $objActSheet = $this->getActiveSheetForGen();
        $objActSheet->setTitle($sheetTitle);
    
        $objActSheet
        ->setCellValue("A1", "父级代码")->setCellValue("B1", "子级代码")->setCellValue("C1", "数量");
    
        $r = 2;
        foreach ($this->bomsInfo as $par => $compsInfo) {
            if ($bomSuffix) {
                if (self::isDashZMaterialCode($par)) {
                    continue;
                }
    
                $par = $par.$bomSuffix;
            }
    
            foreach ($compsInfo as $compInfo)  {
                $comp = $compInfo[0];
                $per = $compInfo[1];
                $op = $compInfo[2];
    
                // if ($this->partsInfo[$comp]["type"] == 'M' && $bomSuffix) {
                // $comp = "$comp-E";
                // }
    
                $objActSheet->setCellValue("A$r", $par)->setCellValue("B$r", $comp)
                ->setCellValue("C$r", $per);
                $r++;
            }
        }
    
        if (!$bomSuffix && $this->altBomsInfo) {
            foreach ($this->altBomsInfo as $par => $compsInfo) {
                foreach ($compsInfo as $compInfo)  {
                    $comp = $compInfo[0];
                    $per = $compInfo[1];
                    $op = $compInfo[2];
    
                    $objActSheet->setCellValue("A$r", $par)->setCellValue("B$r", $comp)
                    ->setCellValue("C$r", $per);
                    $r++;
                }
            }
        }
    
    
        for ($c = 'A'; $c < 'Z'; $c++) {
            $objActSheet->getColumnDimension($c)->setAutoSize(true);
        }
    }
    
    
    protected function generateExcelSheet_13_15_Alt ($sheetTitle)
    {
		$objActSheet = $this->getActiveSheetForGen();
        $objActSheet->setTitle($sheetTitle);
    
        $objActSheet
        ->setCellValue("A1", "bmasmt.p")
        ->setCellValue("A2", "Item Number")->setCellValue("B2", "BOM Code")->setCellValue("C2", "Remarks")->setCellValue("D2", "esc")
        ->setCellValue("A3", "物料代码")->setCellValue("B3", "BOM代码")->setCellValue("C3", "注释")->setCellValue("D3", "esc")
        ->setCellValue("A5", "1/1-C")->setCellValue("B5", "2/1-C")->setCellValue("C5", "3/1-C")->setCellValue("D5", "4/1-C")
        ;
    
        $r = 6;
        foreach ($this->altBomsInfo as $par => $bomInfo) {
			$normalCode = substr($par, 0, -2);
    
            $objActSheet
            ->setCellValue("A$r", $normalCode)
            ->setCellValue("B$r", $par)
            ->setCellValue("C$r", "-")
            ->setCellValue("D$r", ".")
            ;
            $r++;
        }
    
    
        for ($c = 'A'; $c < 'Z'; $c++) {
            $objActSheet->getColumnDimension($c)->setAutoSize(true);
        }
    
    }
    
    
    protected function generateExcelSheet_14_13_1 ($sheetTitle, $bomSuffix = '')
    {
				$objActSheet = $this->getActiveSheetForGen();
        $objActSheet->setTitle($sheetTitle);
        
        $objActSheet
        ->setCellValue("A1", "rwromt.p")
        ->setCellValue("A2", "Routing Code")->setCellValue("B2", "Operation")->setCellValue("D2", "Work Center")->setCellValue("K2", "Milestone Operation")
        ->setCellValue("P2", "Run Time")->setCellValue("T2", "MovetoNextOperation")->setCellValue("U2", "AutoLaborReport")
        ->setCellValue("A3", "工艺流程代码")->setCellValue("B3", "工序")->setCellValue("D3", "工作中心")->setCellValue("K3", "划分阶段的工序")
        ->setCellValue("P3", "加工时间")->setCellValue("T3", "转入下道工序")->setCellValue("U3", "自动人工报表")
        ->setCellValue("V3", "ESC")
        ->setCellValue("A5", "1/1-C")->setCellValue("B5", "1/2-C")->setCellValue("C5", "2/1-C")
        ->setCellValue("D5", "3/1-C")->setCellValue("E5", "3/2-C")->setCellValue("F5", "4/1-C")
        ->setCellValue("G5", "4/2-C")->setCellValue("H5", "4/3-C")->setCellValue("I5", "4/4-C")
        ->setCellValue("J5", "4/5-C")->setCellValue("K5", "4/6-C")->setCellValue("L5", "4/7-C")
        ->setCellValue("M5", "4/8-C")->setCellValue("N5", "4/9-C")->setCellValue("O5", "4/10-C")
        ->setCellValue("P5", "4/11-C")->setCellValue("Q5", "5/1-C")->setCellValue("R5", "5/2-C")
        ->setCellValue("S5", "5/3-C")->setCellValue("T5", "5/4-C")->setCellValue("U5", "5/5-C")
        ->setCellValue("V5", "6/1-C")
        ;
        
        $r = 6;
        foreach ($this->routingInfoList as $routingInfo) {
			$par = $routingInfo[0];
			$op = $routingInfo[1];
            if ($bomSuffix) {
                if (self::isDashZMaterialCode($par)) {
                    continue;
                }
            
                $par = $par.$bomSuffix;
            }
            
            $objActSheet->setCellValue("A$r", $par)->setCellValue("B$r", $op)->setCellValue("C$r", '-')
            ->setCellValue("D$r", '');
            if (empty($op)) {
                // highlight empty op cells (due to uncertainty by part code)
                $objActSheet->getStyle("B$r")->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            }
            for ($c = 'E'; $c <= 'O'; $c++) {
				$objActSheet->setCellValue("$c$r", "-");
            }
			if (in_array($op, [8, 9, 16])) {
				$objActSheet->setCellValue("K$r", "No")->setCellValue("P$r", "0");
			} else {
				$objActSheet->setCellValue("K$r", "Yes")->setCellValue("P$r", "");
			}
			
            $objActSheet
            ->setCellValue("Q$r", "-")->setCellValue("R$r", "-")->setCellValue("S$r", "-")
            ->setCellValue("T$r", "yes")->setCellValue("U$r", "yes")
            ;
            $objActSheet->setCellValue("V$r", ".");
            $r++;
        }
        
        if (!$bomSuffix && $this->altRoutingsInfo) {
            foreach ($this->altRoutingInfoList as $routingInfo) {
				$par = $routingInfo[0];
				$op = $routingInfo[1];
			
                $objActSheet->setCellValue("A$r", $par)->setCellValue("B$r", $op)->setCellValue("C$r", '-')
                ->setCellValue("D$r", '');
                if (empty($op)) {
                    // highlight empty op cells (due to uncertainty by part code)
                    $objActSheet->getStyle("B$r")->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                }
				for ($c = 'E'; $c <= 'O'; $c++) {
					$objActSheet->setCellValue("$c$r", "-");
				}
				if (in_array($op, [8, 9, 16])) {
					$objActSheet->setCellValue("K$r", "No")->setCellValue("P$r", "0");
				} else {
					$objActSheet->setCellValue("K$r", "Yes")->setCellValue("P$r", "0.000000001");
				}
				$objActSheet->setCellValue("P$r", "0.000000001")
				->setCellValue("Q$r", "-")->setCellValue("R$r", "-")->setCellValue("S$r", "-")
				->setCellValue("T$r", "yes")->setCellValue("U$r", "yes")
				;
				$objActSheet->setCellValue("V$r", ".");
				$r++;
            }
        }


        // highlight required user input fields
        foreach (["D", "P"] as $j) {
            for ($i = 6; $i < $r; $i++) {
                $objActSheet->getStyle("$j$i")->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            }
        }
        
        for ($c = 'A'; $c < 'Z'; $c++) {
            $objActSheet->getColumnDimension($c)->setAutoSize(true);
        }
    }
    
    protected function generateExcelSheet_14_15_1_Alt ($sheetTitle)
    {
		$objActSheet = $this->getActiveSheetForGen();
        $objActSheet->setTitle($sheetTitle);
    
        $objActSheet
        ->setCellValue("A1", "rwarmt.p")
        ->setCellValue("A2", "Item Number")->setCellValue("B2", "Site")->setCellValue("C2", "Routing Code")
        ->setCellValue("D2", "Bom Code")->setCellValue("E2", "esc")
        ->setCellValue("A3", "物料代码")->setCellValue("B3", "地点")->setCellValue("C3", "工艺流程代码")
        ->setCellValue("D3", "Bom代码")->setCellValue("E3", "esc")
        ->setCellValue("A5", "1/1-C")->setCellValue("B5", "1/2-C")->setCellValue("C5", "2/1-C")
        ->setCellValue("D5", "3/1-C")->setCellValue("E5", "4/1-C")
        ;
    
        $r = 6;
        foreach ($this->altBomsInfo as $par => $bomInfo) {
			$normalCode = substr($par, 0, -2);
    
            $objActSheet
            ->setCellValue("A$r", $normalCode)->setCellValue("B$r", 1000)
            ->setCellValue("C$r", $par)->setCellValue("D$r", $par)
            ->setCellValue("E$r", ".")
            ;
            $r++;
        }
    
    
        for ($c = 'A'; $c < 'Z'; $c++) {
            $objActSheet->getColumnDimension($c)->setAutoSize(true);
        }
    
    }
	
	
	protected function generateExcelSheet_7_3_13 ($sheetTitle)
    {
		$objActSheet = $this->getActiveSheetForGen();
        $objActSheet->setTitle($sheetTitle);
        
        $objActSheet
        ->setCellValue("A1", "rcsomt.p")
        ->setCellValue("A2", "Ship-from")->setCellValue("B2", "Ship-to")->setCellValue("C2", "Order")
				->setCellValue("I2", "Item Number")->setCellValue("J2", "PO Number")->setCellValue("K2", "Customer Ref")->setCellValue("L2", "Model Year")->setCellValue("M2", "Line")
        ->setCellValue("N2", "copy?")->setCellValue("O2", "Discount Tbl")->setCellValue("P2", "List Price")->setCellValue("Q2", "Net Price")->setCellValue("Z2", "Location")
				->setCellValue("AM2", "Start Effective")->setCellValue("AN2", "endEffective")->setCellValue("AU2", "Netting Logic")->setCellValue("AV2", "esc")
        ->setCellValue("A3", "发货地点")->setCellValue("B3", "发货至")->setCellValue("C3", "订单")
				->setCellValue("I3", "物料号")->setCellValue("J3", "采购订单编号")->setCellValue("K3", "客户参考号")->setCellValue("L3", "模型年")->setCellValue("M3", "行")
        ->setCellValue("N3", "复制?")->setCellValue("O3", "折扣表")->setCellValue("P3", "价目表价格")->setCellValue("Q3", "净价")->setCellValue("Z3", "库位")
				->setCellValue("AM3", "生效日期")->setCellValue("AN3", "失效日期")->setCellValue("AU3", "净需求计算逻辑")->setCellValue("AV3", "esc")
        ->setCellValue("A5", "1/1-C")->setCellValue("B5", "1/2-C")->setCellValue("C5", "1/3-C")
				->setCellValue("D5", "2/1-C")->setCellValue("E5", "3/1-C")->setCellValue("F5", "4/1-C")->setCellValue("G5", "5/1-C")->setCellValue("H5", "6/1-C")
				->setCellValue("I5", "7/1-C")->setCellValue("J5", "7/2-C")->setCellValue("K5", "7/3-C")->setCellValue("L5", "7/4-C")->setCellValue("M5", "7/5-C")
        ->setCellValue("N5", "8/1-C")->setCellValue("O5", "9/1-C")->setCellValue("P5", "9/2-C")->setCellValue("Q5", "9/3-C")
				->setCellValue("R5", "9/4-C")->setCellValue("S5", "9/5-C")->setCellValue("T5", "9/6-C")->setCellValue("U5", "9/7-C")->setCellValue("V5", "9/8-C")->setCellValue("W5", "9/9-C")
				->setCellValue("X5", "9/10-C")->setCellValue("Y5", "9/11-C")->setCellValue("Z5", "9/12-C")
				->setCellValue("AA5", "10/1-C")->setCellValue("AB5", "11/1-C")->setCellValue("AC5", "12/1-C")
				->setCellValue("AD5", "13/1-C")->setCellValue("AE5", "13/2-C")->setCellValue("AF5", "13/3-C")->setCellValue("AG5", "13/4-C")->setCellValue("AH5", "13/5-C")->setCellValue("AI5", "13/6-C")
				->setCellValue("AJ5", "13/7-C")->setCellValue("AK5", "13/8-C")->setCellValue("AL5", "13/9-C")->setCellValue("AM5", "13/10-C")->setCellValue("AN5", "13/11-C")->setCellValue("AO5", "13/12-C")
				->setCellValue("AP5", "13/13-C")->setCellValue("AQ5", "13/14-C")->setCellValue("AR5", "13/15-C")->setCellValue("AS5", "13/16-C")
				->setCellValue("AT5", "13/17-C")->setCellValue("AU5", "13/18-C")->setCellValue("AV5", "14/1-C")
        ;
        
        $r = 6;
        foreach ($this->partsInfo as $partInfo) {
            if (!isset($partInfo["custCode"]) || empty($partInfo["custCode"]) 
				//|| self::isDashZMaterialCode($partInfo["code"])
			) {
                continue;
            }
        
            $objActSheet
            ->setCellValue("D$r", '-')->setCellValue("E$r", '-')->setCellValue("F$r", '-')->setCellValue("G$r", '-')->setCellValue("H$r", '-')
            ->setCellValue("I$r", $partInfo["code"])->setCellValue("J$r", '-')->setCellValue("K$r", '-')->setCellValue("L$r", '-')->setCellValue("M$r", '-')
            ->setCellValue("N$r", 'no')
			->setCellValue("O$r", '-')->setCellValue("P$r", '-')->setCellValue("Q$r", '-')->setCellValue("R$r", '-')->setCellValue("S$r", '-')->setCellValue("T$r", '-')
			->setCellValue("U$r", '-')->setCellValue("V$r", '-')->setCellValue("W$r", '-')->setCellValue("X$r", '-')->setCellValue("Y$r", '-')->setCellValue("Z$r", '')
			->setCellValue("AA$r", '-')->setCellValue("AB$r", '-')->setCellValue("AC$r", '-')->setCellValue("AD$r", '-')->setCellValue("AE$r", '-')->setCellValue("AF$r", '-')
			->setCellValue("AG$r", '-')->setCellValue("AH$r", '-')->setCellValue("AI$r", '-')->setCellValue("AJ$r", '-')->setCellValue("AK$r", '-')->setCellValue("AL$r", '-')
			->setCellValue("AM$r", '-')->setCellValue("AN$r", '-')->setCellValue("AO$r", '-')->setCellValue("AP$r", '-')->setCellValue("AQ$r", '-')->setCellValue("AR$r", '-')
			->setCellValue("AS$r", '-')->setCellValue("AT$r", '-')->setCellValue("AU$r", '3')->setCellValue("AV$r", '.')
            ;
            $r++;
        }
        
        // highlight required user input fields
        foreach (["A", "B", "C", "O", "Z"] as $j) {
            for ($i = 6; $i < $r; $i++) {
                $objActSheet->getStyle("$j$i")->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            }
        }
        
		$c = 'A';
		do {
			$objActSheet->getColumnDimension($c)->setAutoSize(true);
			$c++;
		} while ($c == 'AV');

    }
    
    
    protected function generateExcelSheet_Oa_Circulation ($sheetTitle, $type)
    {
		$objActSheet = $this->getActiveSheetForGen();
        $objActSheet->setTitle($sheetTitle);
    
        $objActSheet
        ->mergeCells("A1:J1")->setCellValue("A1", "导入模板")
        ->setCellValue("A2", "物料类别")->setCellValue("B2", "物料号")->setCellValue("C2", "物料名称")
        ->setCellValue("D2", "物料零件号")->setCellValue("E2", "物料班产")->setCellValue("F2", "物料班人数")
        ->setCellValue("G2", "物料工时")->setCellValue("H2", "供应商代码")->setCellValue("I2", "包装方案")
        ->setCellValue("J2", "物料备注")
        ;
    
        $r = 3;
        $partInfo = [];
        foreach ($this->partsInfo as $partInfo) {
            if ($partInfo["type"] == $type) {
                $partsInfo[] = $partInfo;
            }
        }
        foreach ($partsInfo as $partInfo) {
            $objActSheet
            ->setCellValue("A$r", $type)
            ->setCellValue("B$r", $partInfo["code"])
            ->setCellValue("C$r", $partInfo["desc1"])
            ->setCellValue("D$r", $partInfo["desc2"])
            ->setCellValue("H$r", $partInfo["supCode"])
            ;
            $r++;
        }
        
        for ($c = 'A'; $c < 'Z'; $c++) {
            $objActSheet->getColumnDimension($c)->setAutoSize(true);
        }
    }
    
    
    
    public function test ()
    {
		
		$t1 = time();
		
        $file = './public/excelData/test.xlsx';
        $this->setBomFile($file)->setUsedSheet([2,3]);
		
		$this->ensureInputFormat();
		
		$this->_allowIgnoreFormat = false;
		
        $this->parse();
		

		

		$this->_objExcel = new \PHPExcel();
		
        // 1.4.3
		$this->setUseNewSheetForGen(false);

		$t2 = time();	
        $this->generateExcelSheet_1_4_3('1.4.3');
		$t143 = time();

		$this->setUseNewSheetForGen(true);
        
		

        // 1.16 
        $this->generateExcelSheet_1_6("1.16");

		$t116 = time();

        // // 13.1
        // if ($this->_bomLocs) {
            // if (in_array("NB", $this->_bomLocs) && $this->altBomsInfo) {
                // $this->generateExcelSheet_13_1_Alt("13.1-NB");
            // }
            // if (in_array("CQ", $this->_bomLocs)) {
                // $this->generateExcelSheet_13_1("13.1-CQ", true);
            // }
        // }
        
        
        // // 13.5 
        // if ($this->_bomLocs) {
            // if (in_array("NB", $this->_bomLocs)) {
                // $this->generateExcelSheet_13_5("13.5-NB", false);
            // }
            // if (in_array("CQ", $this->_bomLocs)) {
                // $this->generateExcelSheet_13_5("13.5-CQ", true);
            // }
        // }

        // // 13.15
        // if ($this->altBomsInfo) {
            // $this->generateExcelSheet_13_15_Alt("13.15-NB", false);
        // }
        
        // // 14.13.1
        // if ($this->_bomLocs) {
            // if (in_array("NB", $this->_bomLocs)) {
                // $this->generateExcelSheet_14_13_1("14.13.1-NB", false);
            // }
            // if (in_array("CQ", $this->_bomLocs)) {
                // $this->generateExcelSheet_14_13_1("14.13.1-CQ", true);
            // }
        // }
       
        // // 14.15.1
        // if ($this->altBomsInfo) {
            // $this->generateExcelSheet_14_15_1_Alt("14.15.1-NB");
        // }
        
		// $this->generateExcelSheet_7_3_13("7.3.13-新增寄售");
        
        // // oa tpl
        // if ($this->_genOaTpl) {
            // $this->generateExcelSheet_Oa_Circulation("OA流转单-自制", "M");
            // $this->generateExcelSheet_Oa_Circulation("OA流转单-外购", "B");
        // }
        

		$t3 = time();

        // $this->_objExcel->setActiveSheetIndex(0);
        // //ob_end_clean();
        // $filename = "QADCIM.xls";
        // // header('Content-Type: application/vnd.ms-excel');
        // // header("Content-Disposition: attachment;filename=\"$filename\"");
        // // header('Cache-Control: max-age=0');
        
		// ob_start();
        // $objWriter = \PHPExcel_IOFactory::createWriter($this->_objExcel, 'Excel5');
        // $objWriter->save('php://output');
		
		// file_put_contents('C:\wamp\www\mrp\Public\excelData\\' . $filename, ob_get_clean());
		
		$t4 = time();
		
		
		echo "<br />parse process seconds: " . ($t2 - $t1);
		echo "<br />1.4.3 data generation seconds: " . ($t143 - $t2);
		echo "<br />1.16   data generation seconds: " . ($t116 - $t143);
		echo "<br />output process seconds: " . ($t4 - $t3);
		return;


    }
    
    public function index ()
    {
        $this->display();
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
        $upload->rootPath  =     './Uploads/boms/';
        $upload->autoSub = true;
        $upload->subName = array('date','Ymd');
        $upload->saveName = 'bom_' . time().'_'.mt_rand();;
    
        $info = $upload->upload();
        if(!$info) {
            throw new \Exception($upload->getError());
        } else {
            if (count($info) > 1) {
                throw new \Exception("错误：一次只允许上传一个生产计划文件");
            }
            $file = current($info);
            
            $this->_orgFilename = $file['name'];
            session("orgPeBomFilename", $file['name']);
            
            return $upload->rootPath . $file['savepath'].$file['savename'];
        }
    }
    
    public function importSpecBom ()
    {
        $err = false;
        $msg = '';
        try {
            $this
			->setUsedSheet($_REQUEST["usedSheets"])
            ->setBomLocs($_REQUEST["bomLocs"])
            ->setBomFile($this->getUploadedFile())
            ->setZConfig($_REQUEST["genFromZ"])
            ->setOaConfig($_REQUEST["genOaTpl"])
            ->ensureInputFormat();
            $msg = '工程bom格式文件转换完成';
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
    
    public function exportCimFormatExcel ()
    {
        set_time_limit(900);
        $this->parse()->generateCimFormatExcel();
    }
    
    public function exportStdExcel ()
    {
        set_time_limit(900);
        $this->_allowIgnoreFormat = false;
        $this->parse()->generateStdExcel();
    }
    
    protected function getStdBomData()
    {
        if ($this->_bomLocs) {
            if (in_array("NB", $this->_bomLocs)) {
                $bomSuffix = false;
            }
            if (in_array("CQ", $this->_bomLocs)) {
                $bomSuffix = true;
            }
        }
    
        foreach ($this->bomsInfo as $par => $compsInfo) {
            if ($bomSuffix) {
                if (self::isDashZMaterialCode($par)) {
                    continue;
                }
    
                $par = "$par-E";
            }
    
            foreach ($compsInfo as $compInfo)  {
                $comp = $compInfo[0];
                $per = $compInfo[1];
                $op = $compInfo[2];
    
                $this->_stdBomData[$par][$comp] = $per;
            }
        }
    
        if (!$bomSuffix && $this->altBomsInfo) {
            foreach ($this->altBomsInfo as $par => $compsInfo) {
                foreach ($compsInfo as $compInfo)  {
                    $comp = $compInfo[0];
                    $per = $compInfo[1];
                    $op = $compInfo[2];
    
                    $this->_stdBomData[$par][$comp] = $per;
                }
            }
        }
    
        return $this;
    }
    
    public function CompareDiff ()
    {
        $qadBoms = [];
        foreach ($this->_stdBomData as $par => $orgCompsDetail) {
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
                if (!isset($this->_stdBomData[$par][$qadComp])) {
                    $this->_mismatchInOrg['missing_relations'][$par][$qadComp] = $qty;
                }
            }
        }
        
        return $this;
   
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
        $objActSheet = $this->et();
        $objActStSheet = $this->getActiheet->setTitle($sheetTitle);
    
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
    
    
    public function exportCmpExcel ()
    {
        set_time_limit(900);
        $this->_bomDb = M("ps_mstr");
        $this->_allowIgnoreFormat = false;
        $this->parse()->getStdBomData()->CompareDiff()->generateDiffExcel();
    }
    
    public static function isValidMaterialCode ($partCode)
    {
        // ignore suffix like '-z'
        if (strpos($partCode, "-") > 0) {
            $partCode = strstr($partCode, '-', true);
        }
        
        
        $parts = explode(".", $partCode);
        if (count($parts) < 3) {
            return false;
        }
        
        foreach ($parts as $part) {
            if (!is_numeric($part)) {
                return false;
            }
        }
        
        return true;
    }
    
    public static function isPlaceHolderMaterialCode ($partCode)
    {
        if (strtoupper($partCode{0}) != '#' ) {
            return false;
        }
        
        if (!is_numeric(substr($partCode, 1))) {
            return false;
        }
        
        return true;
    }
    
	public static function isCQBomCode ($bomCode)
    {
        return strtoupper(substr($bomCode, -2)) == '-E';
    }
    
    public static function isDashZMaterialCode ($partCode)
    {
        return strtoupper(substr($partCode, -2)) == '-Z';
    }
    
    public static function getOpByParCode ($par)
    {
		// the op of codes starting with '04.' can not be directly decided 
        if (strtoUpper(substr($par, -2)) == '-Z' || strtoUpper(substr($par, -2)) == '-D') {
            return 20;
        } else if (substr($par, 0, 6) == '03.03.') {
            return 30;
        } else if (substr($par, 0, 6) == '03.02.') {
            return 20;
        } else if (substr($par, 0, 6) == '03.01.') {
            return 10;
        } else {
            return null;
        }
    }
    
    public static function getMainNameOfDesc ($desc)
    {
        // if desc has multiple lines, use the first line as desc, otherwise just use the single line
        if (($rline = strstr($desc, "\r", true)) !== false) {
            $desc = trim($rline);
        } else if (($rline = strstr($desc, "\n", true)) !== false) {
            $desc = trim($rline);
        }
        
        return $desc;
    }
    
    public static function composeFullDesc($orgDesc, $promo, $dir)
    {
        $fullDesc = $orgDesc;
        if (stripos($orgDesc, $promo) !== 0) {
            $fullDesc = "$promo $orgDesc";
        }
        
		if (!preg_match('/(左|右|共用)$/', $orgDesc)) {
			 $fullDesc = "$fullDesc $dir";
		}
        
        return $fullDesc;
    }
    
    public static function isDescOverlength ($desc)
    {
        // convert input utf-8 string to gbk
        $gbkDesc = iconv("utf-8", "gbk", $desc);
        return strlen($gbkDesc) > 24;
    }
    
    public static function isCustCodeOverlength ($custCode)
    {
        // convert input utf-8 string to gbk
        $gbkDesc = iconv("utf-8", "gbk", $custCode);
        return strlen($gbkDesc) > 18;
    }
	

    
}