<?php

/**
 *      我的去向控制器
 *      [X-Mis] (C)2007-2099  
 *      This is NOT a freeware, use is subject to license terms
 *      http://www.xinyou88.com
 *      tel:400-000-9981
 *      qq:16129825
 */

namespace Home\Controller;
use Think\Controller;
use Home\Model\OrderAmountCalculatorModel;

class TestController extends CommonController{

   public function _initialize() {
 
   }
   
   public function index () 
   {
       $compInfo = [
               'dmnd_qtys' => [
                       '2017-03-31' => 100,
                       '2017-04-01' => 300,
                       '2017-04-02' => 200,
                       '2017-04-03' => 400,
                       '2017-04-04' => 200,
                       '2017-04-05' => 300,
                       '2017-04-06' => 100,
                       '2017-04-07' => 200,
                       '2017-04-08' => 150,
                       '2017-04-09' => 300,
                       '2017-04-10' => 250,
                       '2017-04-11' => 200,
                       '2017-04-12' => 250,
                       '2017-04-13' => 280,
                       '2017-04-14' => 330,
                       '2017-04-15' => 180,
                       '2017-04-16' => 150,
                       '2017-04-17' => 200,
                       '2017-04-24' => 400,
                       '2017-05-01' => 500,
                       '2017-06-05' => 1000,
                       '2017-07-03' => 1200,
                       '2017-08-07' => 2100,
               ],
               'tran_qtys' => [
                       '2017-04-01' => 120,  
               ],
               "shop_dates" => [
                       '2017-04-03',
                       '2017-04-17',
                       '2017-05-01',
                       '2017-06-05',
                       '2017-07-03',
                       '2017-08-07',
               ]
       ];
       $options = [
               "demandDateMap" => $compInfo["dmnd_qtys"],
               "transitDateMap" => $compInfo["tran_qtys"],
               "shopDates" => $compInfo["shop_dates"],
               "orgStock" => 150,
               "saftyStock" => 300,
               "baseOrderAmountForMultiple" => 40,
       ];
       
       
       $oac = new \Home\Model\OrderAmountCalculatorModel($options);
       $oac->calculate();
       $compInfo["shop_qtys"] = $oac->getOrderDateMap();
       $compInfo["shop_day_qtys"] = $compInfo["shop_qtys"];
       
       dump($compInfo["shop_day_qtys"]);
       
       $compInfo["stock_qtys"] = $oac->getStockDateMap($invalidStockStartDate);
       $compInfo["invalid_stock_start_date"] = $invalidStockStartDate;
       
   }

}