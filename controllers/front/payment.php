<?php
/**
    * @File: payment.php
    * @Author: Ilko Ivanov
    * @Author e-mail: ilko.iv@gmail.com
    * @Publisher: Avalon Ltd
    * @Publisher e-mail: home@avalonbg.com
    * @Owner: Avalon Ltd
    * @Version: 1.1.1
*/
/**
 * @since 1.5.0
 */
class DskpaymentPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;
    
    /**
     * @see FrontController::initContent()
     */
    public function initContent() {
        parent::initContent();
    
        $cart = $this->context->cart;
        $dskapi_price = floatval($cart->getOrderTotal(true));
        $dskapi_cid = (string)Configuration::get('dskapi_cid');
        $module = Module::getInstanceByName('dskpayment');
        $dskapi_status = (int)Configuration::get('dskapi_status');
        
        if ($dskapi_status > 0) {
            $dskapi_ch = curl_init();
            curl_setopt($dskapi_ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($dskapi_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($dskapi_ch, CURLOPT_MAXREDIRS, 2);
            curl_setopt($dskapi_ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($dskapi_ch, CURLOPT_URL, DSKAPI_LIVEURL . '/function/getminmax.php?cid='.$dskapi_cid);
            $paramsdskapi = json_decode(curl_exec($dskapi_ch), true);
            curl_close($dskapi_ch);
            
            if (empty($paramsdskapi)){
                return null;
            }
            
            $dskapi_minstojnost = floatval($paramsdskapi['dsk_minstojnost']);
            $dskapi_maxstojnost = floatval($paramsdskapi['dsk_maxstojnost']);
            $dskapi_min_000 = floatval($paramsdskapi['dsk_min_000']);
            $dskapi_status_cp = $paramsdskapi['dsk_status'];
            
            $dskapi_purcent = floatval($paramsdskapi['dsk_purcent']);
            $dskapi_vnoski_default = intval($paramsdskapi['dsk_vnoski_default']);
            if (($dskapi_purcent == 0) && ($dskapi_vnoski_default <= 6)){
                $dskapi_minstojnost = $dskapi_min_000;
            }
            
            $dskapi_firstname = isset($this->context->customer->firstname) ? trim($this->context->customer->firstname, " ") : '';
            $dskapi_lastname = isset($this->context->customer->lastname) ? trim($this->context->customer->lastname, " ") : '';
            
            $dskapi_addresses = $this->context->customer->getAddresses($this->context->customer->id_lang);
            $dskapi_address_delivery_id = isset($this->context->cart->id_address_delivery) ? $this->context->cart->id_address_delivery : '';
            $dskapi_address_invoice_id = isset($this->context->cart->id_address_invoice) ? $this->context->cart->id_address_invoice : '';
            foreach ($dskapi_addresses as $dskapi_address){
                if ($dskapi_address['id_address'] == $dskapi_address_delivery_id){
                    $dskapi_shipping_addresses = $dskapi_address;
                }
                if ($dskapi_address['id_address'] == $dskapi_address_invoice_id){
                    $dskapi_billing_addresses = $dskapi_address;
                }
            }
            $dskapi_phone = isset($dskapi_shipping_addresses['phone']) ? $dskapi_shipping_addresses['phone'] : '';
            $dskapi_email = isset($this->context->customer->email) ? $this->context->customer->email : '';
            $dskapi_address_address1 = isset($dskapi_shipping_addresses['address1']) ? $dskapi_shipping_addresses['address1'] : '';
            $dskapi_address_address2 = isset($dskapi_shipping_addresses['address2']) ? $dskapi_shipping_addresses['address2'] : '';
            $dskapi_city = isset($dskapi_shipping_addresses['city']) ? $dskapi_shipping_addresses['city'] : '';
            $dskapi_address2 = $dskapi_address_address2;
            $dskapi_address2city = $dskapi_city;
            $dskapi_address1 = $dskapi_address_address1;
            $dskapi_address1city = $dskapi_city;
            $dskapi_postcode = isset($dskapi_shipping_addresses['postcode']) ? $dskapi_shipping_addresses['postcode'] : '';
            
            $dskapi_eur = 0;
            $dskapi_currency_code = $this->context->currency->iso_code;
            $dskapi_ch_eur = curl_init();
            curl_setopt($dskapi_ch_eur, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($dskapi_ch_eur, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($dskapi_ch_eur, CURLOPT_MAXREDIRS, 3);
            curl_setopt($dskapi_ch_eur, CURLOPT_TIMEOUT, 5);
            curl_setopt($dskapi_ch_eur, CURLOPT_URL, DSKAPI_LIVEURL . '/function/geteur.php?cid=' . $dskapi_cid);
            $paramsdskapieur = json_decode(curl_exec($dskapi_ch_eur), true);
            
            if ($paramsdskapieur != null) {
                $dskapi_eur = (int)$paramsdskapieur['dsk_eur'];
                switch ($dskapi_eur) {
                    case 0:
                        break;
                    case 1:
                        if ($dskapi_currency_code == "EUR") {
                            $dskapi_price = number_format($dskapi_price * 1.95583, 2, ".", "");
                        }
                        break;
                    case 2:
                        $dskapi_sign = "евро";
                        if ($dskapi_currency_code == "BGN") {
                            $dskapi_price = number_format($dskapi_price / 1.95583, 2, ".", "");
                        }
                        break;
                }
            }
            
            if (
                ($dskapi_status_cp != 0) &&
                ($dskapi_price >= $dskapi_minstojnost) &&
                ($dskapi_price <= $dskapi_maxstojnost)
            ) {
                $this->context->smarty->assign(array(
                    'dskapi_nbProducts' => $cart->nbProducts(),
                    'dskapi_this_path_bw' => $this->module->getPathUri(),
                    'dskapi_firstname' => $dskapi_firstname,
                    'dskapi_lastname' => $dskapi_lastname,
                    'dskapi_phone' => $dskapi_phone,
                    'dskapi_email' => $dskapi_email,
                    'dskapi_price' => number_format($dskapi_price, 2, ".", ""),
                    'dskapi_address2' => $dskapi_address2,
                    'dskapi_address2city' => $dskapi_address2city,
                    'dskapi_address1' => $dskapi_address1,
                    'dskapi_address1city' => $dskapi_address1city,
                    'dskapi_postcode' => $dskapi_postcode,
                    'DSKAPI_VERSION' => $module->version,
                    'dskapi_eur' => $dskapi_eur
                ));
                $this->setTemplate('payment_execution.tpl');
            }else{
                return null;
            }
        }else{
            return null;
        }
    }
}