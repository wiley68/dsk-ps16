<?php

/**
 * Control Panel endpoint: purge installment calculation cache for this store.
 *
 * POST /module/dskpayment/cpclearcache
 * Body: cid={dskapi_cid}
 * Origin/Referer or X-Dskapi-Cp-Origin must match DSKAPI_LIVEURL host.
 *
 * @File: cpclearcache.php
 * @Author: Ilko Ivanov
 * @Version: 1.2.2
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../../classes/DskPaymentCpApi.php';

class DskpaymentCpclearcacheModuleFrontController extends ModuleFrontController
{
    /**
     * @var array<string, mixed>
     */
    public $result = array();

    /**
     * @return void
     */
    public function initContent()
    {
        $this->ajax = true;
        $this->result = DskPaymentCpApi::processClearCacheRequest();
        parent::initContent();
    }

    /**
     * @return void
     */
    public function initHeader()
    {
        header('Access-Control-Allow-Origin: ' . DSKAPI_LIVEURL);
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Dskapi-Cp-Origin');

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        parent::initHeader();
    }

    /**
     * @return void
     */
    public function displayAjax()
    {
        $httpStatus = (int) (isset($this->result['_http_status']) ? $this->result['_http_status'] : 200);
        unset($this->result['_http_status']);

        http_response_code($httpStatus);
        header('Content-Type: application/json; charset=utf-8');
        die(Tools::jsonEncode($this->result));
    }
}
