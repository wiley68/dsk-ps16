<?php

/**
 * Front controller for handling order status updates from DSK Bank.
 *
 * This controller receives callbacks from the bank when order status changes
 * and updates the corresponding order status in the PrestaShop database.
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
     * Response data array that will be returned as JSON.
     *
     * @var array
     */
    public $result = array();

    /**
     * Initializes the controller and processes the order status update request.
     *
     * Validates the incoming parameters, verifies the calculator ID,
     * and updates the order status if validation passes.
     *
     * @return void
     */
    public function initContent()
    {
        $this->ajax = true;
        $this->result['success'] = 'unsuccess';

        // Only allow POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->result['error'] = 'Разрешен е само POST метод';
            parent::initContent();
            return;
        }

        // Get configuration
        $dskapi_cid = (string) Configuration::get('dskapi_cid');
        if (empty($dskapi_cid)) {
            $this->result['error'] = 'CID на DSK API не е конфигуриран';
            parent::initContent();
            return;
        }

        // Get and validate order_id
        $dskapi_order_id = (int) Tools::getValue('order_id', 0);
        if ($dskapi_order_id <= 0) {
            $this->result['error'] = 'Невалиден order_id';
            $this->result['dskapi_order_id'] = 0;
            parent::initContent();
            return;
        }

        // Get and validate status (0-8)
        $dskapi_status = (int) Tools::getValue('status', 0);
        if ($dskapi_status < 0 || $dskapi_status > 8) {
            $this->result['error'] = 'Невалиден статус. Трябва да е между 0 и 8';
            $this->result['dskapi_status'] = $dskapi_status;
            parent::initContent();
            return;
        }

        // Get calculator_id for security verification
        $dskapi_calculator_id = (string) Tools::getValue('calculator_id', '');

        // Verify calculator_id matches configured CID
        if (empty($dskapi_calculator_id) || $dskapi_calculator_id !== $dskapi_cid) {
            $this->result['error'] = 'Невалиден calculator_id';
            $this->result['dskapi_order_id'] = $dskapi_order_id;
            $this->result['dskapi_status'] = $dskapi_status;
            $this->result['dskapi_calculator_id'] = $dskapi_calculator_id;
            parent::initContent();
            return;
        }

        // Update order status
        $updateResult = DskPaymentOrder::updateStatus($dskapi_order_id, $dskapi_status);
        if ($updateResult) {
            $this->result['success'] = 'success';
            $this->result['message'] = 'Статусът на поръчката е обновен успешно';
        } else {
            $this->result['error'] = 'Неуспешно обновяване на статуса на поръчката';
        }

        $this->result['dskapi_order_id'] = $dskapi_order_id;
        $this->result['dskapi_status'] = $dskapi_status;
        $this->result['dskapi_calculator_id'] = $dskapi_calculator_id;

        parent::initContent();
    }

    /**
     * Initializes HTTP headers, including CORS headers for bank callbacks.
     *
     * @return void
     */
    public function initHeader()
    {
        header('Access-Control-Allow-Origin: ' . DSKAPI_LIVEURL);
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        // Handle OPTIONS preflight request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        parent::initHeader();
    }

    /**
     * Outputs the response as JSON and terminates execution.
     *
     * @return void
     */
    public function displayAjax()
    {
        header('Content-Type: application/json; charset=utf-8');
        die(Tools::jsonEncode($this->result));
    }
}
