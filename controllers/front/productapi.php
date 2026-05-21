<?php

/**
 * Cached proxy for DSK product API (used by storefront JS).
 *
 * @File: productapi.php
 * @Author: Ilko Ivanov
 * @Version: 1.2.2
 */

require_once dirname(__FILE__) . '/../../classes/DskPaymentApiCache.php';

class DskpaymentProductapiModuleFrontController extends ModuleFrontController
{
    /**
     * @var bool
     */
    public $ssl = true;

    /**
     * @var array|null
     */
    public $result = null;

    /**
     * @return void
     */
    public function initContent()
    {
        $this->ajax = true;

        if ((int) Configuration::get('dskapi_status') === 0) {
            parent::initContent();

            return;
        }

        $cid = (string) Tools::getValue('cid');
        $configCid = (string) Configuration::get('dskapi_cid');

        if ($cid === '' || $cid !== $configCid) {
            parent::initContent();

            return;
        }

        $price = (float) Tools::getValue('price');
        $productId = (int) Tools::getValue('product_id');
        $installments = (int) Tools::getValue('dskapi_vnoski');

        if ($price <= 0) {
            parent::initContent();

            return;
        }

        $this->result = DskPaymentApiCache::fetchProduct($cid, $productId, $price, $installments);

        parent::initContent();
    }

    /**
     * @return void
     */
    public function displayAjax()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        if (!is_array($this->result)) {
            http_response_code(502);
            die('{}');
        }

        die(Tools::jsonEncode($this->result));
    }
}
