<?php

/**
 * DSK Payment Front Controller
 *
 * This controller handles the payment execution page for DSK Bank credit purchases.
 * It validates the cart total against DSK Bank's minimum and maximum limits,
 * retrieves customer data, and prepares all necessary variables for the
 * payment confirmation template.
 *
 * @File: payment.php
 * @Author: Ilko Ivanov
 * @Author e-mail: ilko.iv@gmail.com
 * @Publisher: Avalon Ltd
 * @Publisher e-mail: home@avalonbg.com
 * @Owner: Банка ДСК
 * @Version: 1.2.2
 * @since 1.2.1
 */

require_once dirname(__FILE__) . '/../../classes/DskPaymentApiCache.php';

class DskpaymentPaymentModuleFrontController extends ModuleFrontController
{
    /**
     * Force SSL connection for security
     *
     * @var bool
     */
    public $ssl = true;

    /**
     * Hide left column for cleaner payment page layout
     *
     * @var bool
     */
    public $display_column_left = false;

    /**
     * Initialize page content and prepare payment data
     *
     * This method performs the following operations:
     * - Validates that the DSK payment module is active
     * - Checks cart total against DSK Bank's min/max limits
     * - Retrieves customer and address information
     * - Handles currency conversion (BGN/EUR)
     * - Prepares data for the interest rates popup
     * - Assigns all variables to Smarty template
     *
     * @return void|string Returns empty string if validation fails
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        /** @var Cart $cart */
        $cart = $this->context->cart;
        $dskapi_price = (float) $cart->getOrderTotal(true);
        $dskapi_cid = (string) Configuration::get('dskapi_cid');
        $module = Module::getInstanceByName('dskpayment');
        $dskapi_status = (int) Configuration::get('dskapi_status');

        // Check if module is enabled
        if ($dskapi_status == 0) {
            return '';
        }

        // Fetch min/max limits from DSK API
        $paramsdskapi = $this->makeApiRequest('/function/getminmax.php?cid=' . $dskapi_cid, 6);
        if ($paramsdskapi === null) {
            return '';
        }

        // Parse API response for price limits
        $dskapi_minstojnost = (float) $paramsdskapi['dsk_minstojnost'];
        $dskapi_maxstojnost = (float) $paramsdskapi['dsk_maxstojnost'];
        $dskapi_min_000 = (float) $paramsdskapi['dsk_min_000'];
        $dskapi_status_cp = $paramsdskapi['dsk_status'];

        // Adjust minimum for 0% interest rate with <= 6 months
        $dskapi_purcent = (float) $paramsdskapi['dsk_purcent'];
        $dskapi_vnoski_default = (int) $paramsdskapi['dsk_vnoski_default'];
        if (($dskapi_purcent == 0) && ($dskapi_vnoski_default <= 6)) {
            $dskapi_minstojnost = $dskapi_min_000;
        }

        // Get customer personal data
        $dskapi_firstname = isset($this->context->customer->firstname) ? trim($this->context->customer->firstname, " ") : '';
        $dskapi_lastname = isset($this->context->customer->lastname) ? trim($this->context->customer->lastname, " ") : '';

        // Get customer addresses
        $dskapi_addresses = $this->context->customer->getAddresses($this->context->customer->id_lang);
        $dskapi_address_delivery_id = isset($this->context->cart->id_address_delivery) ? $this->context->cart->id_address_delivery : '';
        $dskapi_address_invoice_id = isset($this->context->cart->id_address_invoice) ? $this->context->cart->id_address_invoice : '';

        // Find shipping and billing addresses
        foreach ($dskapi_addresses as $dskapi_address) {
            if ($dskapi_address['id_address'] == $dskapi_address_delivery_id) {
                $dskapi_shipping_addresses = $dskapi_address;
            }
            if ($dskapi_address['id_address'] == $dskapi_address_invoice_id) {
                $dskapi_billing_addresses = $dskapi_address;
            }
        }

        // Extract address details with fallback to empty strings
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

        // Handle currency conversion settings
        $dskapi_eur = 0;
        $dskapi_currency_code = $this->context->currency->iso_code;

        // Fetch EUR conversion settings from API
        $paramsdskapieur = $this->makeApiRequest('/function/geteur.php?cid=' . urlencode($dskapi_cid));
        if ($paramsdskapieur === null) {
            return '';
        }

        // Apply currency conversion based on settings
        $dskapi_sign = 'лв.';
        $dskapi_eur = (int) $paramsdskapieur['dsk_eur'];
        switch ($dskapi_eur) {
            case 0:
                // No conversion
                break;
            case 1:
                // Convert to BGN
                $dskapi_sign = 'лв.';
                if ($dskapi_currency_code == "EUR") {
                    $dskapi_price = (float) number_format($dskapi_price * 1.95583, 2, ".", "");
                }
                break;
            case 2:
                // Convert to EUR
                $dskapi_sign = "евро";
                if ($dskapi_currency_code == "BGN") {
                    $dskapi_price = (float) number_format($dskapi_price / 1.95583, 2, ".", "");
                }
                break;
        }

        // Validate cart total is within allowed range
        if (
            ($dskapi_status_cp == 0) ||
            ($dskapi_price < $dskapi_minstojnost) ||
            ($dskapi_price > $dskapi_maxstojnost)
        ) {
            return '';
        }

        // Fetch data for interest rates popup
        $dskapi_product_id = $this->resolveCartProductId();
        $paramsdskapi_popup = DskPaymentApiCache::fetchProduct($dskapi_cid, $dskapi_product_id, $dskapi_price, 0);

        // Initialize popup data with defaults
        $dskapi_popup_enabled = false;
        $dskapi_vnoski_visible_arr = [];
        $dskapi_vnoski = 0;
        $dskapi_vnoska = 0;

        // Parse popup data if available
        if ($paramsdskapi_popup !== null && isset($paramsdskapi_popup['dsk_options'])) {
            $dskapi_popup_enabled = true;
            $dskapi_vnoski_visible = (int) (isset($paramsdskapi_popup['dsk_vnoski_visible']) ? $paramsdskapi_popup['dsk_vnoski_visible'] : 0);
            $dskapi_vnoski = (int) (isset($paramsdskapi_popup['dsk_vnoski_default']) ? $paramsdskapi_popup['dsk_vnoski_default'] : 0);
            $dskapi_vnoska = (float) (isset($paramsdskapi_popup['dsk_vnoska']) ? $paramsdskapi_popup['dsk_vnoska'] : 0);

            // Build array of visible installment options using bitmask
            for ($vnoska = 3; $vnoska <= 48; $vnoska++) {
                $bitPosition = $vnoska - 3;
                $bitMask = 1 << $bitPosition;
                $dskapi_vnoski_visible_arr[$vnoska] = ($dskapi_vnoski_visible & $bitMask) !== 0 || $dskapi_vnoski === $vnoska;
            }
        }

        $bannerUrls = Dskpayment::getPopupBannerUrls(
            isset($paramsdskapi_popup['dsk_reklama']) ? $paramsdskapi_popup['dsk_reklama'] : 0
        );

        // Assign all variables to Smarty template
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
            'dskapi_eur' => $dskapi_eur,
            // Interest rates popup data
            'dskapi_popup_enabled' => $dskapi_popup_enabled,
            'dskapi_cid' => $dskapi_cid,
            'dskapi_product_id' => $dskapi_product_id,
            'DSKAPI_LIVEURL' => DSKAPI_LIVEURL,
            'DSKAPI_PRODUCT_API_URL' => $this->context->link->getModuleLink('dskpayment', 'productapi', array(), true),
            'dskapi_sign' => $dskapi_sign,
            'dskapi_currency_code' => $dskapi_currency_code,
            'dskapi_vnoski' => $dskapi_vnoski,
            'dskapi_vnoska' => number_format($dskapi_vnoska, 2, '.', ''),
            'dskapi_vnoski_visible_arr' => $dskapi_vnoski_visible_arr,
            'dskapi_maxstojnost' => (float) number_format($dskapi_maxstojnost, 2, '.', ''),
            'dskapi_picture_desktop' => $bannerUrls['desktop'],
            'dskapi_picture_mobile' => $bannerUrls['mobile'],
        ));

        $this->setTemplate('payment_execution.tpl');
    }

    /**
     * Resolve product ID from the cart
     *
     * Determines the product identifier to send to the DSK API.
     * If the cart contains exactly one unique product, its ID is returned.
     * If multiple products exist, returns 0 (generic cart calculation).
     *
     * @return int Product ID or 0 for mixed cart
     */
    private function resolveCartProductId()
    {
        if (!$this->context->cart instanceof Cart) {
            return 0;
        }

        $products = $this->context->cart->getProducts(true);
        if (empty($products)) {
            return 0;
        }

        // Collect unique product IDs
        $uniqueIds = [];
        foreach ($products as $product) {
            $productId = (int) ($product['id_product'] ?? 0);
            if ($productId > 0) {
                $uniqueIds[$productId] = true;
            }
            // Return 0 if multiple different products
            if (count($uniqueIds) > 1) {
                return 0;
            }
        }

        reset($uniqueIds);
        $firstKey = key($uniqueIds);

        return (int) ($firstKey ?? 0);
    }

    /**
     * Execute an HTTP request to the DSK Bank API
     *
     * Sends a GET request to the specified DSK API endpoint and returns
     * the decoded JSON response. Uses cURL with SSL verification disabled
     * for compatibility with various server configurations.
     *
     * @param string $endpoint Relative API endpoint path (e.g., '/function/getminmax.php?cid=xxx')
     * @param int $timeout Request timeout in seconds (default: 5)
     *
     * @return array|null Decoded JSON response as associative array, or null on failure
     */
    private function makeApiRequest($endpoint, $timeout = 5)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_URL, DSKAPI_LIVEURL . $endpoint);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Validate response
        if ($response === false || $httpCode !== 200 || !empty($curlError)) {
            return null;
        }

        // Decode JSON response
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}
