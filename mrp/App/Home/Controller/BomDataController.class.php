<?php

namespace Home\Controller;
use Think\Controller;

class BomDataController extends CommonController
{
    private $_syncDateTime;
    
    public function _initialize() {
        parent::_initialize();
        $this->dbname = "bom_detail";
        $this->_syncDateTime = M("tbl_uptime")->where(["tbl_name" => "ps_mstr"])->getField("tbl_update_time");
    }
    
    protected function _search ()
    {
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

    public function _befor_index()
    {
         $this->assign("syncDateTime", $this->_syncDateTime);
    }
    
    public function outxls() 
    {
        $model = D($this->dbname);
        $map = $this->_search();
        if (method_exists($this, '_filter')) {
            $this->_filter($map);
        }
        $list = $model->where($map)->field('ps_site,par_promo, ps_par, par_desc1, par_desc2, ps_comp, comp_desc1, comp_desc2, ps_qty_per, ps_op, ps_start, ps_end ')->select();
        $headArr=array('地点','父级项目','父级代码','父级描述','父级型号','子级代码','子级描述','子级型号','用量', '工序', '生效日期', '失效日期');
        $filename='BOM数据';
        $this->xlsout($filename,$headArr,$list);
    }
    
    // 上传图片插件
    public function upload()
    {
        /*		$upload = new \Think\Upload();// 实例化上传类
         $upload->maxSize   =     3145728 ;// 设置附件上传大小
         $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
         $upload->savePath  =      './Public/Uploads/'; // 设置附件上传目录
         // 上传文件
         $info   =   $upload->upload();
         if(!$info) {
         // 上传错误提示错误信息
         $this->error($upload->getError());
         }else{
         // 上传成功
         $this->success('上传成功！');
         }
         */
    
        $uploaddir = 'Public/images/uploads/';
        $uploadfile = $uploaddir.date("YmdHis").".jpg";
    
        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
            echo json_encode(array('statusCode'=> 200,'message'=>"上传成功！","filename"=>$uploadfile));
        } else {
            echo json_encode(array('statusCode'=> 300,'message'=>"上传失败！","filename"=>$uploadfile));
        }
    }
    
}
