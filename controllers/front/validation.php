<?php

/**
 * DSK Payment Order Validation Controller
 *
 * This front controller handles the final validation and submission of orders
 * to DSK Bank for credit processing. It performs the following operations:
 * - Validates cart and customer data
 * - Creates a PrestaShop order with DSK payment status
 * - Encrypts order data using RSA public key
 * - Sends encrypted data to DSK Bank API
 * - Redirects customer to DSK Bank application form
 *
 * @File: validation.php
 * @Author: Ilko Ivanov
 * @Author e-mail: ilko.iv@gmail.com
 * @Publisher: Avalon Ltd
 * @Publisher e-mail: home@avalonbg.com
 * @Owner: Банка ДСК
 * @Version: 1.2.0
 * @since 1.2.0
 */
class DskpaymentValidationModuleFrontController extends ModuleFrontController
{
    /**
     * Process payment validation and submit order to DSK Bank
     *
     * This method handles the complete order submission workflow:
     * 1. Validates cart has customer, delivery and invoice addresses
     * 2. Checks if dskpayment module is authorized for payments
     * 3. Creates PrestaShop order with DSK payment order state
     * 4. Collects customer and product data from POST parameters
     * 5. Handles currency conversion (BGN/EUR)
     * 6. Encrypts data using RSA public key encryption
     * 7. Sends encrypted data to DSK Bank API
     * 8. Creates DSK payment order record in database
     * 9. Redirects to DSK Bank application form (mobile or desktop)
     *
     * @return void
     *
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        /** @var Cart $cart */
        $cart = $this->context->cart;

        // Validate cart has required data
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
            Tools::redirect('index.php?controller=order&step=1');

        // Check if dskpayment module is authorized for payments
        $authorized = false;
        foreach (Module::getPaymentModules() as $module)
            if ($module['name'] == 'dskpayment') {
                $authorized = true;
                break;
            }

        if (!$authorized)
            die($this->module->l('Този метод на плащане не е достъпен.', 'validation'));

        // Validate customer exists
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

        $currency = $this->context->currency;
        $module = Module::getInstanceByName('dskpayment');

        $dskapi_total = (float)$cart->getOrderTotal(true);

        // Mail variables for order confirmation (legacy, not used by DSK)
        $mailVars = array(
            '{bankwire_owner}' => 'owner',
            '{bankwire_details}' => nl2br('details'),
            '{bankwire_address}' => nl2br('address')
        );

        // Create PrestaShop order with DSK payment status
        $this->module->validateOrder(
            $cart->id,
            Configuration::get('PS_OS_DSKPAYMENT'),
            $dskapi_total,
            $this->module->displayName,
            NULL,
            $mailVars,
            (int)$currency->id,
            false,
            $customer->secure_key
        );

        // Get customer data from POST parameters with fallbacks
        if (null !== Tools::getValue('dskapi_firstname')) {
            $dskapi_firstname = Tools::getValue('dskapi_firstname');
        } else {
            $dskapi_firstname = '';
        }
        if (null !== Tools::getValue('dskapi_lastname')) {
            $dskapi_lastname = Tools::getValue('dskapi_lastname');
        } else {
            $dskapi_lastname = '';
        }
        if (null !== Tools::getValue('dskapi_phone')) {
            $dskapi_phone = Tools::getValue('dskapi_phone');
        } else {
            $dskapi_phone = '';
        }
        if (null !== Tools::getValue('dskapi_email')) {
            $dskapi_email = Tools::getValue('dskapi_email');
        } else {
            $dskapi_email = '';
        }
        if (null !== Tools::getValue('dskapi_address2')) {
            $dskapi_address2 = Tools::getValue('dskapi_address2');
        } else {
            $dskapi_address2 = '';
        }
        if (null !== Tools::getValue('dskapi_address2city')) {
            $dskapi_address2city = Tools::getValue('dskapi_address2city');
        } else {
            $dskapi_address2city = '';
        }
        if (null !== Tools::getValue('dskapi_address1')) {
            $dskapi_address1 = Tools::getValue('dskapi_address1');
        } else {
            $dskapi_address1 = '';
        }
        if (null !== Tools::getValue('dskapi_address1city')) {
            $dskapi_address1city = Tools::getValue('dskapi_address1city');
        } else {
            $dskapi_address1city = '';
        }
        if (null !== Tools::getValue('dskapi_postcode')) {
            $dskapi_postcode = Tools::getValue('dskapi_postcode');
        } else {
            $dskapi_postcode = '';
        }
        if (null !== Tools::getValue('dskapi_eur')) {
            $dskapi_eur = (int)Tools::getValue('dskapi_eur');
        } else {
            $dskapi_eur = 0;
        }

        // Get module configuration
        $dskapi_cid = (string)Configuration::get('dskapi_cid');

        // Detect if customer is on mobile device
        $dskapi_type_client = $this->detectMobileDevice();

        $order_id = $this->module->currentOrder;
        $dskapi_currency_code = $this->context->currency->iso_code;
        $dskapi_currency_code_send = 0;

        // Handle currency conversion based on EUR settings
        switch ($dskapi_eur) {
            case 0:
                // No conversion
                break;
            case 1:
                // Convert to BGN
                $dskapi_currency_code_send = 0;
                if ($dskapi_currency_code == "EUR") {
                    $dskapi_total = number_format($dskapi_total * 1.95583, 2, ".", "");
                }
                break;
            case 2:
                // Convert to EUR
                $dskapi_currency_code_send = 1;
                if ($dskapi_currency_code == "BGN") {
                    $dskapi_total = number_format($dskapi_total / 1.95583, 2, ".", "");
                }
                break;
        }

        // Build product data strings for API submission
        $products = $cart->getProducts(true);
        $products_id = '';
        $products_q = '';
        $products_p = '';
        $products_name = '';
        $products_c = '';
        $products_m = '';
        $products_i = '';

        foreach ($products as $product) {
            // Product ID
            $products_id .= strval($product['id_product']);
            $products_id .= '_';

            // Quantity
            $products_q .= strval($product['quantity']);
            $products_q .= '_';

            // Price with currency conversion
            $products_p_temp = (float)$product['price_wt'];
            switch ($dskapi_eur) {
                case 0:
                    break;
                case 1:
                    if ($dskapi_currency_code == "EUR") {
                        $products_p_temp = $products_p_temp * 1.95583;
                    }
                    break;
                case 2:
                case 3:
                    if ($dskapi_currency_code == "BGN") {
                        $products_p_temp = $products_p_temp / 1.95583;
                    }
                    break;
            }
            $products_p .= number_format($products_p_temp, 2, ".", "");
            $products_p .= '_';

            // Product name (sanitized)
            $products_name .= str_replace('"', '', str_replace("'", "", htmlspecialchars_decode($product['name'], ENT_QUOTES)));
            $products_name .= '_';

            // Category ID
            $products_c .= strval($product['id_category_default']);
            $products_c .= '_';

            // Manufacturer ID
            $products_m .= strval($product['id_manufacturer']);
            $products_m .= '_';

            // Product image URL (base64 encoded)
            $dskapi_image = Image::getCover($product['id_product']);
            $dskapi_link = new Link;
            $dskapi_imagePath = $dskapi_link->getImageLink($product['link_rewrite'], $dskapi_image['id_image'], 'home_default');
            if (!preg_match("~^(?:f|ht)tps?://~i", $dskapi_imagePath)) {
                $dskapi_imagePath = "https://" . $dskapi_imagePath;
            }
            $dskapi_imagePath_64 = base64_encode($dskapi_imagePath);
            $products_i .= $dskapi_imagePath_64;
            $products_i .= '_';
        }

        // Remove trailing underscores from product strings
        $products_id = trim($products_id, "_");
        $products_q = trim($products_q, "_");
        $products_p = trim($products_p, "_");
        $products_c = trim($products_c, "_");
        $products_m = trim($products_m, "_");
        $products_name = trim($products_name, "_");
        $products_i = trim($products_i, "_");

        // Build API request payload
        $dskapi_post = [
            'unicid' => $dskapi_cid,
            'first_name' => htmlspecialchars_decode($dskapi_firstname, ENT_QUOTES),
            'last_name' => htmlspecialchars_decode($dskapi_lastname, ENT_QUOTES),
            'phone' => $dskapi_phone,
            'email' => $dskapi_email,
            'address2' => str_replace('"', '', str_replace("'", "", htmlspecialchars_decode($dskapi_address2, ENT_QUOTES))),
            'address2city' => str_replace('"', '', str_replace("'", "", htmlspecialchars_decode($dskapi_address2city, ENT_QUOTES))),
            'postcode' => $dskapi_postcode,
            'price' => $dskapi_total,
            'address' => str_replace('"', '', str_replace("'", "", htmlspecialchars_decode($dskapi_address1, ENT_QUOTES))),
            'addresscity' => str_replace('"', '', str_replace("'", "", htmlspecialchars_decode($dskapi_address1city, ENT_QUOTES))),
            'products_id' => $products_id,
            'products_name' => $products_name,
            'products_q' => $products_q,
            'type_client' => $dskapi_type_client,
            'products_p' => $products_p,
            'version' => $module->version,
            'shoporder_id'    => $order_id,
            'products_c'    => $products_c,
            'products_m'    => $products_m,
            'products_i'    => $products_i,
            'currency' => $dskapi_currency_code_send
        ];

        // Encrypt data using RSA public key
        $dskapi_plaintext = json_encode($dskapi_post);
        $dskapi_publicKey = openssl_pkey_get_public(file_get_contents(_PS_MODULE_DIR_ . 'dskpayment/keys/pub.pem'));
        $dskapi_a_key = openssl_pkey_get_details($dskapi_publicKey);
        $dskapi_chunkSize = ceil($dskapi_a_key['bits'] / 8) - 11;
        $dskapi_output = '';

        // Encrypt data in chunks (RSA has size limits)
        while ($dskapi_plaintext) {
            $dskapi_chunk = substr($dskapi_plaintext, 0, $dskapi_chunkSize);
            $dskapi_plaintext = substr($dskapi_plaintext, $dskapi_chunkSize);
            $dskapi_encrypted = '';
            if (!openssl_public_encrypt($dskapi_chunk, $dskapi_encrypted, $dskapi_publicKey)) {
                die('Failed to encrypt data');
            }
            $dskapi_output .= $dskapi_encrypted;
        }

        // Free key resource (PHP < 8.0 compatibility)
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            openssl_free_key($dskapi_publicKey);
        }
        $dskapi_output64 = base64_encode($dskapi_output);

        // Send encrypted order data to DSK Bank API
        $dskapi_add_ch = curl_init();
        curl_setopt_array($dskapi_add_ch, array(
            CURLOPT_URL => DSKAPI_LIVEURL . '/function/addorders.php',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode(array('data' => $dskapi_output64)),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "cache-control: no-cache"
            ),
        ));
        $curl_response = curl_exec($dskapi_add_ch);
        $curl_error = curl_error($dskapi_add_ch);
        $curl_http_code = curl_getinfo($dskapi_add_ch, CURLINFO_HTTP_CODE);
        curl_close($dskapi_add_ch);

        // Handle cURL execution error
        if ($curl_response === false || !empty($curl_error)) {
            DskPaymentOrder::create($order_id, 0);

            // Send notification email about communication failure
            $headers  = 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-type: text/plain; charset=UTF-8;' . "\r\n";
            mail(DSKAPI_MAIL, 'Проблем комуникация заявка КП DSK Credit', json_encode($dskapi_post, JSON_PRETTY_PRINT), $headers);

            die($this->module->l('Има временен проблем с комуникацията към DSK Credit. Изпратен е мейл с Вашата заявка към Банката. Моля очаквайте обратна връзка от Банката за да продължите процедурата по вашата заявка за кредит.', 'validation'));
        }

        // Handle non-200 HTTP response
        if ($curl_http_code !== 200) {
            DskPaymentOrder::create($order_id, 0);

            // Send notification email about HTTP error
            $headers  = 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-type: text/plain; charset=UTF-8;' . "\r\n";
            mail(DSKAPI_MAIL, 'Проблем комуникация заявка КП DSK Credit', json_encode($dskapi_post, JSON_PRETTY_PRINT), $headers);

            die($this->module->l('Има временен проблем с комуникацията към DSK Credit. Изпратен е мейл с Вашата заявка към Банката. Моля очаквайте обратна връзка от Банката за да продължите процедурата по вашата заявка за кредит.', 'validation'));
        }

        // Parse API response
        $paramsdskapiadd = json_decode($curl_response, true);

        // Handle successful API response with order_id
        if (
            (!empty($paramsdskapiadd)) &&
            isset($paramsdskapiadd['order_id']) &&
            ($paramsdskapiadd['order_id'] != 0) &&
            DskPaymentOrder::create($order_id, 0)
        ) {
            // Redirect to appropriate DSK Bank application form (mobile or desktop)
            if ($dskapi_type_client == 1) {
                Tools::redirect(DSKAPI_LIVEURL . '/applicationm_step1.php?oid=' . $paramsdskapiadd['order_id'] . '&cid=' . $dskapi_cid);
            } else {
                Tools::redirect(DSKAPI_LIVEURL . '/application_step1.php?oid=' . $paramsdskapiadd['order_id'] . '&cid=' . $dskapi_cid);
            }
        } else {
            // Create record with status 0 on failure
            DskPaymentOrder::create($order_id, 0);

            if (empty($paramsdskapiadd)) {
                // Empty response - send notification email
                $headers  = 'MIME-Version: 1.0' . "\r\n";
                $headers .= 'Content-type: text/plain; charset=UTF-8;' . "\r\n";
                mail(DSKAPI_MAIL, 'Проблем комуникация заявка КП DSK Credit', json_encode($dskapi_post, JSON_PRETTY_PRINT), $headers);

                die($this->module->l('Има временен проблем с комуникацията към DSK Credit. Изпратен е мейл с Вашата заявка към Банката. Моля очаквайте обратна връзка от Банката за да продължите процедурата по вашата заявка за кредит.', 'validation'));
            } else {
                // Order already exists in DSK system
                die($this->module->l('Вече има създадена заявка за кредит в системата на DSK Credit с номер на Вашия ордер: ' . $order_id, 'validation'));
            }
        }
    }

    /**
     * Detect if the visitor is using a mobile device
     *
     * Analyzes the User-Agent header to determine if the request
     * originates from a mobile device. This is used to redirect
     * to the appropriate DSK Bank application form (mobile or desktop).
     *
     * @return int Returns 1 for mobile devices, 0 for desktop
     */
    private function detectMobileDevice(): int
    {
        $useragent = array_key_exists('HTTP_USER_AGENT', $_SERVER) ? $_SERVER['HTTP_USER_AGENT'] : '';

        if (empty($useragent)) {
            return 0;
        }

        // Mobile device detection using regex patterns
        $mobilePattern = '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i';
        $mobilePatternShort = '/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i';

        if (preg_match($mobilePattern, $useragent) || preg_match($mobilePatternShort, substr($useragent, 0, 4))) {
            return 1;
        }

        return 0;
    }
}
