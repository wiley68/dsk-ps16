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
 * @Version: 1.2.0
 * @since 1.5.0
 */
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
        $apiUrl = '/function/getproduct.php?cid=' . urlencode($dskapi_cid)
            . '&price=' . urlencode((string) $dskapi_price)
            . '&product_id=' . urlencode((string) $dskapi_product_id);
        $paramsdskapi_popup = $this->makeApiRequest($apiUrl);

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

        // Determine CSS class prefixes for mobile/desktop
        $dskapi_is_mobile = $this->isMobileDevice();
        $prefix = $dskapi_is_mobile ? 'dskapim' : 'dskapi';
        $imgPrefix = $dskapi_is_mobile ? 'dskm' : 'dsk';

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
            'dskapi_sign' => $dskapi_sign,
            'dskapi_currency_code' => $dskapi_currency_code,
            'dskapi_vnoski' => $dskapi_vnoski,
            'dskapi_vnoska' => number_format($dskapi_vnoska, 2, '.', ''),
            'dskapi_vnoski_visible_arr' => $dskapi_vnoski_visible_arr,
            'dskapi_maxstojnost' => (float) number_format($dskapi_maxstojnost, 2, '.', ''),
            'dskapi_PopUp_Detailed_v1' => $prefix . '_PopUp_Detailed_v1',
            'dskapi_Mask' => $prefix . '_Mask',
            'dskapi_picture' => DSKAPI_LIVEURL . '/calculators/assets/img/' . $imgPrefix . (isset($paramsdskapi_popup['dsk_reklama']) ? $paramsdskapi_popup['dsk_reklama'] : 0) . '.png',
            'dskapi_product_name' => $prefix . '_product_name',
            'dskapi_body_panel_txt3' => $prefix . '_body_panel_txt3',
            'dskapi_body_panel_txt4' => $prefix . '_body_panel_txt4',
            'dskapi_body_panel_txt3_left' => $prefix . '_body_panel_txt3_left',
            'dskapi_body_panel_txt3_right' => $prefix . '_body_panel_txt3_right',
            'dskapi_sumi_panel' => $prefix . '_sumi_panel',
            'dskapi_kredit_panel' => $prefix . '_kredit_panel',
            'dskapi_body_panel_footer' => $prefix . '_body_panel_footer',
            'dskapi_body_panel_left' => $prefix . '_body_panel_left'
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
     * Detect if the current visitor uses a mobile device
     *
     * Uses User-Agent string analysis to determine if the request
     * comes from a mobile device. This affects the CSS class prefixes
     * used in the payment template for responsive styling.
     *
     * @return bool True if mobile device detected, false otherwise
     */
    private function isMobileDevice()
    {
        $useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($useragent)) {
            return false;
        }

        $mobilePattern = '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i';

        return (bool) preg_match($mobilePattern, $useragent)
            || (bool) preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4));
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
