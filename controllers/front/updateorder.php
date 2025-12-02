<?php
class DskpaymentUpdateorderModuleFrontController extends ModuleFrontController {
    public $result = array();
    
    public function initContent() {
        $this->ajax = true;
        $this->result['success'] = 'unsuccess';
        
        $dskapi_cid = (string)Configuration::get('dskapi_cid');
        
        if (null !== Tools::getValue('order_id')) {
            $dskapi_order_id = Tools::getValue('order_id');
        } else {
            $dskapi_order_id = '';
        }
        if (null !== Tools::getValue('status')) {
            $dskapi_status = Tools::getValue('status');
        } else {
            $dskapi_status = 0;
        }
        if (null !== Tools::getValue('calculator_id')) {
            $dskapi_calculator_id = Tools::getValue('calculator_id');
        } else {
            $dskapi_calculator_id = '';
        }
        
        if (($dskapi_calculator_id != '') && ($dskapi_cid == $dskapi_calculator_id)){
            if (file_exists(_PS_MODULE_DIR_ . 'dskpayment/keys/dskapiorders.json')) {
                $orderdata = file_get_contents(_PS_MODULE_DIR_ . 'dskpayment/keys/dskapiorders.json');
                $dskapi_orderdata_all = json_decode($orderdata, true);
                foreach ($dskapi_orderdata_all as $key => $value){
                    if ($dskapi_orderdata_all[$key]['order_id'] == $dskapi_order_id){
                        $dskapi_orderdata_all[$key]['order_status'] = $dskapi_status;
                    }
                }
                $jsondata = json_encode($dskapi_orderdata_all);
                file_put_contents(_PS_MODULE_DIR_ . 'dskpayment/keys/dskapiorders.json', $jsondata);
                $this->result['success'] = 'success';
            }
        }
        
        $this->result['dskapi_order_id'] = $dskapi_order_id;
        $this->result['dskapi_status'] = $dskapi_status;
        $this->result['dskapi_calculator_id'] = $dskapi_calculator_id;
        
        parent::initContent();
    }
    
    public function initHeader(){
        header('Access-Control-Allow-Origin: ' . DSKAPI_LIVEURL);
        return parent::initHeader();
    }
    
    public function displayAjax()
    {
        die(Tools::jsonEncode($this->result));
    }
}