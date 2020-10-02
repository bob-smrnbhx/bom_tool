<?php
namespace Wap\Service;

abstract class CommonService {
   
    protected function getM() {
        return M($this->getModelName());
    }


    protected function getD() {
        return D($this->getModelName());
    }


    protected function isRelation() {
        return true;
    }


}
