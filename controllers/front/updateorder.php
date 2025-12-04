<?php

/**
 * DSK Bank Order Status Update Controller
 *
 * This front controller handles webhook callbacks from DSK Bank when order
 * status changes occur. It receives POST requests with order_id, status,
 * and calculator_id parameters, validates them, and updates the corresponding
 * DSK payment order record in the database.
 *
 * Endpoint: /module/dskpayment/updateorder
 *
 * Expected POST parameters:
 * - order_id: PrestaShop order ID (int)
 * - status: DSK Bank status code 0-8 (int)
 * - calculator_id: Store's DSK calculator ID for security verification (string)
 *
 * @File: updateorder.php
 * @Author: Ilko Ivanov
 * @Author e-mail: ilko.iv@gmail.com
 * @Publisher: Avalon Ltd
 * @Publisher e-mail: home@avalonbg.com
 * @Owner: Банка ДСК
 * @Version: 1.2.0
 */
class DskpaymentUpdateorderModuleFrontController extends ModuleFrontController
{
    /**
     * Response data array that will be returned as JSON
     *
     * Contains success/error status and debug information
     * about the processed request.
     *
     * @var array
     */
    public $result = array();

    /**
     * Initialize controller and process order status update request
     *
     * Performs the following validations:
     * - Request method must be POST
     * - DSK API CID must be configured in module settings
     * - order_id must be a positive integer
     * - status must be between 0 and 8
     * - calculator_id must match the configured CID
     *
     * If all validations pass, updates the order status in the database.
     *
     * @return void
     */
    public function initContent()
    {
        $this->ajax = true;
        $this->result['success'] = 'unsuccess';

        // Only allow POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->result['error'] = 'Only POST method is allowed';
            parent::initContent();
            return;
        }

        // Get and validate module configuration
        $dskapi_cid = (string) Configuration::get('dskapi_cid');
        if (empty($dskapi_cid)) {
            $this->result['error'] = 'DSK API CID is not configured';
            parent::initContent();
            return;
        }

        // Get and validate order_id parameter
        $dskapi_order_id = (int) Tools::getValue('order_id', 0);
        if ($dskapi_order_id <= 0) {
            $this->result['error'] = 'Invalid order_id';
            $this->result['dskapi_order_id'] = 0;
            parent::initContent();
            return;
        }

        // Get and validate status parameter (must be 0-8)
        $dskapi_status = (int) Tools::getValue('status', 0);
        if ($dskapi_status < 0 || $dskapi_status > 8) {
            $this->result['error'] = 'Invalid status. Must be between 0 and 8';
            $this->result['dskapi_status'] = $dskapi_status;
            parent::initContent();
            return;
        }

        // Get calculator_id for security verification
        $dskapi_calculator_id = (string) Tools::getValue('calculator_id', '');

        // Verify calculator_id matches configured CID (security check)
        if (empty($dskapi_calculator_id) || $dskapi_calculator_id !== $dskapi_cid) {
            $this->result['error'] = 'Invalid calculator_id';
            $this->result['dskapi_order_id'] = $dskapi_order_id;
            $this->result['dskapi_status'] = $dskapi_status;
            $this->result['dskapi_calculator_id'] = $dskapi_calculator_id;
            parent::initContent();
            return;
        }

        // Update order status in database
        $updateResult = DskPaymentOrder::updateStatus($dskapi_order_id, $dskapi_status);
        if ($updateResult) {
            $this->result['success'] = 'success';
            $this->result['message'] = 'Order status updated successfully';
        } else {
            $this->result['error'] = 'Failed to update order status';
        }

        // Include request parameters in response for debugging
        $this->result['dskapi_order_id'] = $dskapi_order_id;
        $this->result['dskapi_status'] = $dskapi_status;
        $this->result['dskapi_calculator_id'] = $dskapi_calculator_id;

        parent::initContent();
    }

    /**
     * Initialize HTTP headers including CORS for bank callbacks
     *
     * Sets up Cross-Origin Resource Sharing (CORS) headers to allow
     * the DSK Bank server to make requests to this endpoint.
     * Also handles OPTIONS preflight requests.
     *
     * @return void
     */
    public function initHeader()
    {
        // Set CORS headers for DSK Bank server
        header('Access-Control-Allow-Origin: ' . DSKAPI_LIVEURL);
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        // Handle OPTIONS preflight request for CORS
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        parent::initHeader();
    }

    /**
     * Output response as JSON and terminate execution
     *
     * Sets the appropriate Content-Type header and outputs
     * the result array as a JSON-encoded string.
     *
     * @return void
     */
    public function displayAjax()
    {
        header('Content-Type: application/json; charset=utf-8');
        die(Tools::jsonEncode($this->result));
    }
}
