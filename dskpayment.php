<?php

/**
 * @File: dskpayment.php
 * @Author: Ilko Ivanov
 * @Author e-mail: ilko.iv@gmail.com
 * @Publisher: Avalon Ltd
 * @Publisher e-mail: home@avalonbg.com
 * @Owner: Банка ДСК
 * @Version: 1.2.0
 */
if (!defined('_PS_VERSION_'))
    exit;

defined('DSKAPI_LIVEURL') or define('DSKAPI_LIVEURL', 'https://dsk.avalon-bg.eu');
defined('DSKAPI_MAIL') or define('DSKAPI_MAIL', 'home@avalonbg.com');
// PrestaShop constants (defined by core, but added here for IDE support)
defined('_DB_PREFIX_') or define('_DB_PREFIX_', 'ps_');
defined('_MYSQL_ENGINE_') or define('_MYSQL_ENGINE_', 'InnoDB');

/**
 * Class Dskpayment
 * @extends PaymentModule
 */
class Dskpayment extends PaymentModule
{

    const HOOKS = [
        'header',
        'displayRightColumnProduct',
        'payment',
        'displayHome',
        'displayPaymentEU',
        'displayReassurance',
        'displayHome'
    ];

    public function __construct()
    {
        $this->name = 'dskpayment';
        $this->tab = 'payments_gateways';
        $this->version = '1.2.0';
        $this->author = 'Ilko Ivanov';
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.6.99');
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Банка ДСК покупки на Кредит');
        $this->description = $this->l('Дава възможност на Вашите клиенти да закупуват стока на изплащане с Банка ДСК.');
        $this->confirmUninstall = $this->l('Сигурни ли сте, че желаете да го деинсталирате?');
        if (!Configuration::get('DSKPAYMENT_NAME'))
            $this->warning = $this->l('Не е зададено име');
    }

    public function install()
    {
        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);

        // Install parent module first
        if (!parent::install()) {
            return false;
        }

        // Register hooks
        foreach (static::HOOKS as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        // Set configuration
        if (!Configuration::updateValue('DSKPAYMENT_NAME', 'Банка ДСК покупки на Кредит')) {
            return false;
        }

        // Install database tables
        if (!$this->installDb()) {
            return false;
        }

        // Install order states
        if (!$this->installOrderStates()) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        if (
            !parent::uninstall() ||
            !Configuration::deleteByName('DSKPAYMENT_NAME') ||
            !Configuration::deleteByName('PS_OS_DSKPAYMENT') ||
            !Configuration::deleteByName('dskapi_status') ||
            !Configuration::deleteByName('dskapi_cid') ||
            !Configuration::deleteByName('dskapi_reklama') ||
            !Configuration::deleteByName('dskapi_gap') ||
            !$this->uninstallDb() ||
            !$this->uninstallOrderStates()
        )
            return false;
        return true;
    }

    /**
     * Creates required database tables for recurring plans and orders log.
     *
     * @return bool True on success, false otherwise
     */
    private function installDb()
    {
        $sql_orders = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "dskpayment_orders` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `order_id` INT(11) NOT NULL,
            `order_status` TINYINT(4) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `order_id` (`order_id`),
            KEY `order_status` (`order_status`)
        ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;";

        $db = Db::getInstance();
        if (!$db->execute($sql_orders)) {
            return false;
        }

        return true;
    }

    /**
     * Drops database tables on module uninstall.
     *
     * @return bool True on success, false otherwise
     */
    private function uninstallDb()
    {
        $sql = "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "dskpayment_orders`;";
        $db = Db::getInstance();
        return (bool) $db->execute($sql);
    }

    private function installOrderStates()
    {
        return $this->createOrderState(
            'PS_OS_DSKPAYMENT',
            'Банка ДСК',
            '#3d70b2',
            true,
            true,
            false
        );
    }

    private function uninstallOrderStates()
    {
        $id_state = (int) Configuration::get('PS_OS_DSKPAYMENT');
        if ($id_state > 0) {
            /** @var OrderState $orderState */
            $orderState = new OrderState($id_state);
            if (Validate::isLoadedObject($orderState)) {
                $orderState->delete();
            }
        }

        return true;
    }

    private function createOrderState($configKey, $name, $color, $logable, $invoice, $hidden)
    {
        $id_state = (int) Configuration::get($configKey);
        if ($id_state > 0 && OrderState::existsInDatabase($id_state, 'order_state')) {
            return true;
        }

        $existingId = (int) Db::getInstance()->getValue(
            (new DbQuery())
                ->select('id_order_state')
                ->from('order_state')
                ->where('module_name = \'' . pSQL($this->name) . '\'')
                ->where('hidden = ' . (int) $hidden)
                ->orderBy('id_order_state DESC')
        );

        if ($existingId > 0 && OrderState::existsInDatabase($existingId, 'order_state')) {
            $orderState = new OrderState($existingId);
            if (Validate::isLoadedObject($orderState)) {
                $orderState->color = $color;
                $orderState->logable = $logable;
                $orderState->invoice = $invoice;
                $orderState->send_email = false;
                $orderState->hidden = $hidden;
                $orderState->unremovable = false;
                $orderState->active = true;
                $orderState->module_name = $this->name;

                $languages = Language::getLanguages(false);
                foreach ($languages as $language) {
                    $idLang = (int) $language['id_lang'];
                    $orderState->name[$idLang] = $this->l($name);
                }

                $orderState->update();
            }

            Configuration::updateValue($configKey, $existingId);

            return true;
        }

        /** @var OrderState $orderState */
        $orderState = new OrderState();
        $orderState->color = $color;
        $orderState->logable = $logable;
        $orderState->invoice = $invoice;
        $orderState->send_email = false;
        $orderState->hidden = $hidden;
        $orderState->unremovable = false;
        $orderState->active = true;
        $orderState->module_name = $this->name;

        /** @var array<int, array<string, mixed>> $languages */
        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            $idLang = (int) $language['id_lang'];
            $orderState->name[$idLang] = $this->l($name);
        }

        if (!$orderState->add()) {
            return false;
        }

        Configuration::updateValue($configKey, (int) $orderState->id);

        return true;
    }

    public function getContent()
    {
        $output = null;
        if (Tools::isSubmit('submit' . $this->name)) {
            $dskapi_status = intval(Tools::getValue('dskapi_status'));
            $dskapi_cid = strval(Tools::getValue('dskapi_cid'));
            $dskapi_reklama = intval(Tools::getValue('dskapi_reklama'));
            $dskapi_gap = strval(Tools::getValue('dskapi_gap'));

            Configuration::updateValue('dskapi_status', $dskapi_status);
            Configuration::updateValue('dskapi_cid', $dskapi_cid);
            Configuration::updateValue('dskapi_reklama', $dskapi_reklama);
            Configuration::updateValue('dskapi_gap', $dskapi_gap);

            $output .= $this->displayConfirmation($this->l('The changes have been successfully saved'));
        }
        return $output . $this->displayForm();
    }

    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => 'Настройки',
            ),
            'input' => array(
                array(
                    'type'      => 'radio',
                    'label'     => 'DSK Credit API покупки на Кредит',
                    'desc'      => 'Дава възможност на Вашите клиенти да закупуват стока на изплащане с DSK Credit API.',
                    'name'      => 'dskapi_status',
                    'required'  => true,
                    'class'     => 't',
                    'is_bool'   => true,
                    'values'    => array(
                        array(
                            'id'    => 'dskapi_active_on',
                            'value' => 1,
                            'label' => 'Разреши'
                        ),
                        array(
                            'id'    => 'dskapi_active_off',
                            'value' => 0,
                            'label' => 'Забрани'
                        )
                    ),
                ),
                array(
                    'type'      => 'text',
                    'label'     => $this->l('Уникален идентификатор на магазина'),
                    'desc'      => $this->l('Уникален идентификатор на магазина в системата на DSK Credit API.'),
                    'name'      => 'dskapi_cid',
                    'size'  => 36,
                    'required'  => true
                ),
                array(
                    'type'      => 'radio',
                    'label'     => 'Визуализиране на реклама',
                    'desc'      => 'Можете да включвате или изключвате показването на реклама в началната страница на магазина.',
                    'name'      => 'dskapi_reklama',
                    'required'  => true,
                    'class'     => 't',
                    'is_bool'   => true,
                    'values'    => array(
                        array(
                            'id'    => 'dskapi_active_rek_on',
                            'value' => 1,
                            'label' => 'Разреши'
                        ),
                        array(
                            'id'    => 'dskapi_active_rek_off',
                            'value' => 0,
                            'label' => 'Забрани'
                        )
                    ),
                ),
                array(
                    'type'      => 'text',
                    'label'     => 'Свободно място над бутона',
                    'desc'      => 'Свободно място над бутона в px.',
                    'name'      => 'dskapi_gap',
                    'size'  => 100,
                    'required'  => true
                ),
            ),
            'submit' => array(
                'title' => $this->l('Запиши'),
                'class' => 'button'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' =>
            array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                    '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['dskapi_status'] = Configuration::get('dskapi_status');
        $helper->fields_value['dskapi_cid'] = Configuration::get('dskapi_cid');
        $helper->fields_value['dskapi_reklama'] = Configuration::get('dskapi_reklama');
        $helper->fields_value['dskapi_gap'] = Configuration::get('dskapi_gap') == "" ? 0 : Configuration::get('dskapi_gap');

        //show order statuses
        $tempcontent = file_get_contents(_PS_MODULE_DIR_ . 'dskpayment/keys/dskapiorders.json');
        $dsk_orders = json_decode($tempcontent);
        $add_options = "";
        $htmljs = '<script type="text/javascript" src="../modules/dskpayment/js/dskapi_admin.js"></script>';

        $add_options .= '<div class="form-group row">';
        $add_options .= '<div class="col-sm-12">';
        $add_options .= '<button type="button" class="button" onclick="show_dskapi_all_orders();">Покажи състоянието на всички ордери от DSK Credit</button>';
        $add_options .= '</div>';
        $add_options .= '</div>';

        $add_options .= '<div class="form-group row">';
        $add_options .= '<div class="col-sm-6">Ордер ID номер. Идентификатора на ордер в системата. Id #:</div>';
        $add_options .= '<div class="col-sm-6">Статус на ордера. Статус на ордера в DSK Credit:</div>';
        $add_options .= '</div>';

        $add_options .= '<div id="dskapi_orders_div" style="display:none;width:100%;height:200px;overflow:auto;">';
        foreach (array_reverse($dsk_orders) as $dsk_order) {
            switch ($dsk_order->order_status) {
                case 0:
                    $dskapi_status_txt = "Създадена Апликация";
                    break;
                case 1:
                    $dskapi_status_txt = "Избрана финансова схема";
                    break;
                case 2:
                    $dskapi_status_txt = "Попълнена Апликация";
                    break;
                case 3:
                    $dskapi_status_txt = "Изпратен Банка";
                    break;
                case 4:
                    $dskapi_status_txt = "Неуспешен контакт с клиента";
                    break;
                case 5:
                    $dskapi_status_txt = "Анулирана апликация";
                    break;
                case 6:
                    $dskapi_status_txt = "Отказана апликация";
                    break;
                case 7:
                    $dskapi_status_txt = "Подписан договор";
                    break;
                case 8:
                    $dskapi_status_txt = "Усвоен кредит";
                    break;
                default:
                    $dskapi_status_txt = "Създадена Апликация";
                    break;
            }
            $add_options .= '<div class="form-group row" style="width:95%;">';
            $add_options .= '<div class="col-sm-6"><input type="text" disabled class="form-control" value="' . $dsk_order->order_id . '" /></div>';
            $add_options .= '<div class="col-sm-6"><input type="text" disabled class="form-control" value="' . $dskapi_status_txt . '" /></div>';
            $add_options .= '</div>';
        }
        $add_options .= '</div>';

        return $helper->generateForm($fields_form) . $add_options . $htmljs;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module))
            foreach ($currencies_module as $currency_module)
                if ($currency_order->id == $currency_module['id_currency'])
                    return true;
        return false;
    }

    public function hookDisplayHome($params)
    {

        $this->context->controller->addCSS($this->_path . 'css/dskapi_rek.css');

        $dskapi_cid = (string)Configuration::get('dskapi_cid');
        $dskapi_reklama = (int)Configuration::get('dskapi_reklama');
        $dskapi_status = (int)Configuration::get('dskapi_status');

        if (($dskapi_reklama > 0) && ($dskapi_status > 0)) {
            $dskapi_ch = curl_init();
            curl_setopt($dskapi_ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($dskapi_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($dskapi_ch, CURLOPT_MAXREDIRS, 2);
            curl_setopt($dskapi_ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($dskapi_ch, CURLOPT_URL, DSKAPI_LIVEURL . '/function/getrek.php?cid=' . $dskapi_cid);
            $paramsdskapi = json_decode(curl_exec($dskapi_ch), true);
            curl_close($dskapi_ch);

            $useragent = array_key_exists('HTTP_USER_AGENT', $_SERVER) ? $_SERVER['HTTP_USER_AGENT'] : '';
            if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4))) {
                $dskapi_deviceis = "mobile";
            } else {
                $dskapi_deviceis = "pc";
            }

            $dskapi_picture = $paramsdskapi['dsk_picture'];
            $dskapi_container_txt1 = $paramsdskapi['dsk_container_txt1'];
            $dskapi_container_txt2 = $paramsdskapi['dsk_container_txt2'];
            $dskapi_logo_url = $paramsdskapi['dsk_logo_url'];

            if ((!empty($paramsdskapi)) && ($paramsdskapi['dsk_status'] == 1) && ($paramsdskapi['dsk_container_status'] == 1)) {
                $this->context->smarty->assign(
                    array(
                        'dskapi_deviceis' => $dskapi_deviceis,
                        'DSKAPI_LIVEURL' => DSKAPI_LIVEURL,
                        'dskapi_picture' => $dskapi_picture,
                        'dskapi_container_txt1' => $dskapi_container_txt1,
                        'dskapi_container_txt2' => $dskapi_container_txt2,
                        'dskapi_logo_url' => $dskapi_logo_url
                    )
                );
                return $this->display(__FILE__, 'dskapipanel.tpl');
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    public function hookDisplayHeader($params)
    {
        if (isset($this->context->controller->php_self) && $this->context->controller->php_self == 'product') {
            Tools::addJS($this->_path . 'js/dskapi_product.js', 'all');
            Tools::addCSS($this->_path . 'css/dskapi_product.css', 'all');
        }
    }

    public function hookPayment($params)
    {
        if (!$this->active)
            return;
        if (!$this->checkCurrency($params['cart']))
            return;

        $dskapi_status = (int)Configuration::get('dskapi_status');
        $dskapi_currency_code = $this->context->currency->iso_code;
        if ($dskapi_status == 0 || ($dskapi_currency_code != 'EUR' && $dskapi_currency_code != 'BGN'))
            return null;

        $dskapi_cid = (string)Configuration::get('dskapi_cid');
        $cart = $this->context->cart;
        $dskapi_price = floatval($cart->getOrderTotal(true));

        $dskapi_ch = curl_init();
        curl_setopt($dskapi_ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($dskapi_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($dskapi_ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($dskapi_ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($dskapi_ch, CURLOPT_URL, DSKAPI_LIVEURL . '/function/getminmax.php?cid=' . $dskapi_cid);
        $paramsdskapi = json_decode(curl_exec($dskapi_ch), true);
        curl_close($dskapi_ch);

        if (empty($paramsdskapi))
            return null;

        $dskapi_eur = (int)$paramsdskapi['dsk_eur'];
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
        $dskapi_minstojnost = floatval($paramsdskapi['dsk_minstojnost']);
        $dskapi_maxstojnost = floatval($paramsdskapi['dsk_maxstojnost']);
        $dskapi_min_000 = floatval($paramsdskapi['dsk_min_000']);
        $dskapi_status_cp = $paramsdskapi['dsk_status'];

        $dskapi_purcent = floatval($paramsdskapi['dsk_purcent']);
        $dskapi_vnoski_default = intval($paramsdskapi['dsk_vnoski_default']);
        if (($dskapi_purcent == 0) && ($dskapi_vnoski_default <= 6)) {
            $dskapi_minstojnost = $dskapi_min_000;
        }

        if (($dskapi_status_cp == 0) || ($dskapi_price < $dskapi_minstojnost) || ($dskapi_price > $dskapi_maxstojnost)) {
            return null;
        } else {
            $this->smarty->assign(array(
                'dskapithis_path' => $this->_path,
                'dskapithis_path_bw' => $this->_path,
                'dskapithis_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
            ));
            return $this->display(__FILE__, 'payment.tpl');
        }
    }

    public function hookDisplayPaymentEU($params)
    {
        if (!$this->active)
            return;
        if (!$this->checkCurrency($params['cart']))
            return;

        $payment_options = array(
            'cta_text' => $this->l('Pay by DSK API Credit'),
            'action' => $this->context->link->getModuleLink($this->name, 'validation', array(), true)
        );
        return $payment_options;
    }

    public function hookDisplayRightColumnProduct($params)
    {
        if ($this->context->controller instanceof ProductController) {
            $dskapi_status = (int)Configuration::get('dskapi_status');
            $dskapi_currency_code = $this->context->currency->iso_code;
            $dskapi_gap = (int)Configuration::get('dskapi_gap');

            if ($dskapi_status != 0 && ($dskapi_currency_code == 'EUR' || $dskapi_currency_code == 'BGN')) {
                $dskapi_cid = (string)Configuration::get('dskapi_cid');
                $dskapi_product_id = (int)Tools::getValue('id_product');
                $product = new Product($dskapi_product_id);
                $dskapi_price = (float)Product::getPriceStatic($dskapi_product_id, true);

                $dskapi_eur = 0;
                $dskapi_sign = "лв.";
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
                            $dskapi_sign = "лв.";
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

                $dskapi_ch = curl_init();
                curl_setopt($dskapi_ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($dskapi_ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($dskapi_ch, CURLOPT_MAXREDIRS, 3);
                curl_setopt($dskapi_ch, CURLOPT_TIMEOUT, 6);
                curl_setopt($dskapi_ch, CURLOPT_URL, DSKAPI_LIVEURL . '/function/getproduct.php?cid=' . $dskapi_cid . '&price=' . $dskapi_price . '&product_id=' . $dskapi_product_id);
                $paramsdskapi = json_decode(curl_exec($dskapi_ch), true);
                curl_close($dskapi_ch);

                if (empty($paramsdskapi) || $paramsdskapi['dsk_status'] == 0) {
                    return null;
                }

                $dskapi_zaglavie = $paramsdskapi['dsk_zaglavie'];
                $dskapi_custom_button_status = intval($paramsdskapi['dsk_custom_button_status']);
                $dskapi_options = boolval($paramsdskapi['dsk_options']);
                $dskapi_is_visible = boolval($paramsdskapi['dsk_is_visible']);
                $dskapi_button_normal = DSKAPI_LIVEURL . '/calculators/assets/img/buttons/dsk.png';
                $dskapi_button_normal_custom = DSKAPI_LIVEURL . '/calculators/assets/img/custom_buttons/' . $dskapi_cid . '.png';
                $dskapi_button_hover = DSKAPI_LIVEURL . '/calculators/assets/img/buttons/dsk-hover.png';
                $dskapi_button_hover_custom = DSKAPI_LIVEURL . '/calculators/assets/img/custom_buttons/' . $dskapi_cid . '_hover.png';
                $dskapi_isvnoska = intval($paramsdskapi['dsk_isvnoska']);
                $dskapi_vnoski = intval($paramsdskapi['dsk_vnoski_default']);
                $dskapi_vnoska = floatval($paramsdskapi['dsk_vnoska']);
                $dskapi_button_status = intval($paramsdskapi['dsk_button_status']);
                $dskapi_minstojnost = number_format(floatval($paramsdskapi['dsk_minstojnost']), 2, ".", "");
                $dskapi_maxstojnost = number_format(floatval($paramsdskapi['dsk_maxstojnost']), 2, ".", "");
                $dskapi_vnoski_visible = intval($paramsdskapi['dsk_vnoski_visible']);

                $dskapi_vnoski_visible_arr = array();
                if ($dskapi_vnoski_visible & 1) {
                    $dskapi_vnoski_visible_arr[3] = true;
                } else {
                    $dskapi_vnoski_visible_arr[3] = false;
                    if ($dskapi_vnoski == 3) {
                        $dskapi_vnoski_visible_arr[3] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 2) {
                    $dskapi_vnoski_visible_arr[4] = true;
                } else {
                    $dskapi_vnoski_visible_arr[4] = false;
                    if ($dskapi_vnoski == 4) {
                        $dskapi_vnoski_visible_arr[4] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 4) {
                    $dskapi_vnoski_visible_arr[5] = true;
                } else {
                    $dskapi_vnoski_visible_arr[5] = false;
                    if ($dskapi_vnoski == 5) {
                        $dskapi_vnoski_visible_arr[5] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 8) {
                    $dskapi_vnoski_visible_arr[6] = true;
                } else {
                    $dskapi_vnoski_visible_arr[6] = false;
                    if ($dskapi_vnoski == 6) {
                        $dskapi_vnoski_visible_arr[6] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 16) {
                    $dskapi_vnoski_visible_arr[7] = true;
                } else {
                    $dskapi_vnoski_visible_arr[7] = false;
                    if ($dskapi_vnoski == 7) {
                        $dskapi_vnoski_visible_arr[7] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 32) {
                    $dskapi_vnoski_visible_arr[8] = true;
                } else {
                    $dskapi_vnoski_visible_arr[8] = false;
                    if ($dskapi_vnoski == 8) {
                        $dskapi_vnoski_visible_arr[8] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 64) {
                    $dskapi_vnoski_visible_arr[9] = true;
                } else {
                    $dskapi_vnoski_visible_arr[9] = false;
                    if ($dskapi_vnoski == 9) {
                        $dskapi_vnoski_visible_arr[9] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 128) {
                    $dskapi_vnoski_visible_arr[10] = true;
                } else {
                    $dskapi_vnoski_visible_arr[10] = false;
                    if ($dskapi_vnoski == 10) {
                        $dskapi_vnoski_visible_arr[10] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 256) {
                    $dskapi_vnoski_visible_arr[11] = true;
                } else {
                    $dskapi_vnoski_visible_arr[11] = false;
                    if ($dskapi_vnoski == 11) {
                        $dskapi_vnoski_visible_arr[11] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 512) {
                    $dskapi_vnoski_visible_arr[12] = true;
                } else {
                    $dskapi_vnoski_visible_arr[12] = false;
                    if ($dskapi_vnoski == 12) {
                        $dskapi_vnoski_visible_arr[12] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 1024) {
                    $dskapi_vnoski_visible_arr[13] = true;
                } else {
                    $dskapi_vnoski_visible_arr[13] = false;
                    if ($dskapi_vnoski == 13) {
                        $dskapi_vnoski_visible_arr[13] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 2048) {
                    $dskapi_vnoski_visible_arr[14] = true;
                } else {
                    $dskapi_vnoski_visible_arr[14] = false;
                    if ($dskapi_vnoski == 14) {
                        $dskapi_vnoski_visible_arr[14] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 4096) {
                    $dskapi_vnoski_visible_arr[15] = true;
                } else {
                    $dskapi_vnoski_visible_arr[15] = false;
                    if ($dskapi_vnoski == 15) {
                        $dskapi_vnoski_visible_arr[15] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 8192) {
                    $dskapi_vnoski_visible_arr[16] = true;
                } else {
                    $dskapi_vnoski_visible_arr[16] = false;
                    if ($dskapi_vnoski == 16) {
                        $dskapi_vnoski_visible_arr[16] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 16384) {
                    $dskapi_vnoski_visible_arr[17] = true;
                } else {
                    $dskapi_vnoski_visible_arr[17] = false;
                    if ($dskapi_vnoski == 18) {
                        $dskapi_vnoski_visible_arr[18] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 32768) {
                    $dskapi_vnoski_visible_arr[18] = true;
                } else {
                    $dskapi_vnoski_visible_arr[18] = false;
                    if ($dskapi_vnoski == 19) {
                        $dskapi_vnoski_visible_arr[19] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 65536) {
                    $dskapi_vnoski_visible_arr[19] = true;
                } else {
                    $dskapi_vnoski_visible_arr[19] = false;
                    if ($dskapi_vnoski == 19) {
                        $dskapi_vnoski_visible_arr[19] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 131072) {
                    $dskapi_vnoski_visible_arr[20] = true;
                } else {
                    $dskapi_vnoski_visible_arr[20] = false;
                    if ($dskapi_vnoski == 20) {
                        $dskapi_vnoski_visible_arr[20] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 262144) {
                    $dskapi_vnoski_visible_arr[21] = true;
                } else {
                    $dskapi_vnoski_visible_arr[21] = false;
                    if ($dskapi_vnoski == 21) {
                        $dskapi_vnoski_visible_arr[21] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 524288) {
                    $dskapi_vnoski_visible_arr[22] = true;
                } else {
                    $dskapi_vnoski_visible_arr[22] = false;
                    if ($dskapi_vnoski == 22) {
                        $dskapi_vnoski_visible_arr[22] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 1048576) {
                    $dskapi_vnoski_visible_arr[23] = true;
                } else {
                    $dskapi_vnoski_visible_arr[23] = false;
                    if ($dskapi_vnoski == 23) {
                        $dskapi_vnoski_visible_arr[23] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 2097152) {
                    $dskapi_vnoski_visible_arr[24] = true;
                } else {
                    $dskapi_vnoski_visible_arr[24] = false;
                    if ($dskapi_vnoski == 24) {
                        $dskapi_vnoski_visible_arr[24] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 4194304) {
                    $dskapi_vnoski_visible_arr[25] = true;
                } else {
                    $dskapi_vnoski_visible_arr[25] = false;
                    if ($dskapi_vnoski == 25) {
                        $dskapi_vnoski_visible_arr[25] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 8388608) {
                    $dskapi_vnoski_visible_arr[26] = true;
                } else {
                    $dskapi_vnoski_visible_arr[26] = false;
                    if ($dskapi_vnoski == 26) {
                        $dskapi_vnoski_visible_arr[26] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 16777216) {
                    $dskapi_vnoski_visible_arr[27] = true;
                } else {
                    $dskapi_vnoski_visible_arr[27] = false;
                    if ($dskapi_vnoski == 27) {
                        $dskapi_vnoski_visible_arr[27] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 33554432) {
                    $dskapi_vnoski_visible_arr[28] = true;
                } else {
                    $dskapi_vnoski_visible_arr[28] = false;
                    if ($dskapi_vnoski == 28) {
                        $dskapi_vnoski_visible_arr[28] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 67108864) {
                    $dskapi_vnoski_visible_arr[29] = true;
                } else {
                    $dskapi_vnoski_visible_arr[29] = false;
                    if ($dskapi_vnoski == 29) {
                        $dskapi_vnoski_visible_arr[29] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 134217728) {
                    $dskapi_vnoski_visible_arr[30] = true;
                } else {
                    $dskapi_vnoski_visible_arr[30] = false;
                    if ($dskapi_vnoski == 30) {
                        $dskapi_vnoski_visible_arr[30] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 268435456) {
                    $dskapi_vnoski_visible_arr[31] = true;
                } else {
                    $dskapi_vnoski_visible_arr[31] = false;
                    if ($dskapi_vnoski == 31) {
                        $dskapi_vnoski_visible_arr[31] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 536870912) {
                    $dskapi_vnoski_visible_arr[32] = true;
                } else {
                    $dskapi_vnoski_visible_arr[32] = false;
                    if ($dskapi_vnoski == 32) {
                        $dskapi_vnoski_visible_arr[32] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 1073741824) {
                    $dskapi_vnoski_visible_arr[33] = true;
                } else {
                    $dskapi_vnoski_visible_arr[33] = false;
                    if ($dskapi_vnoski == 33) {
                        $dskapi_vnoski_visible_arr[33] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 2147483648) {
                    $dskapi_vnoski_visible_arr[34] = true;
                } else {
                    $dskapi_vnoski_visible_arr[34] = false;
                    if ($dskapi_vnoski == 34) {
                        $dskapi_vnoski_visible_arr[34] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 4294967296) {
                    $dskapi_vnoski_visible_arr[35] = true;
                } else {
                    $dskapi_vnoski_visible_arr[35] = false;
                    if ($dskapi_vnoski == 35) {
                        $dskapi_vnoski_visible_arr[35] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 8589934592) {
                    $dskapi_vnoski_visible_arr[36] = true;
                } else {
                    $dskapi_vnoski_visible_arr[36] = false;
                    if ($dskapi_vnoski == 36) {
                        $dskapi_vnoski_visible_arr[36] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 17179869184) {
                    $dskapi_vnoski_visible_arr[37] = true;
                } else {
                    $dskapi_vnoski_visible_arr[37] = false;
                    if ($dskapi_vnoski == 37) {
                        $dskapi_vnoski_visible_arr[37] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 34359738368) {
                    $dskapi_vnoski_visible_arr[38] = true;
                } else {
                    $dskapi_vnoski_visible_arr[38] = false;
                    if ($dskapi_vnoski == 38) {
                        $dskapi_vnoski_visible_arr[38] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 68719476736) {
                    $dskapi_vnoski_visible_arr[39] = true;
                } else {
                    $dskapi_vnoski_visible_arr[39] = false;
                    if ($dskapi_vnoski == 39) {
                        $dskapi_vnoski_visible_arr[39] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 137438953472) {
                    $dskapi_vnoski_visible_arr[40] = true;
                } else {
                    $dskapi_vnoski_visible_arr[40] = false;
                    if ($dskapi_vnoski == 40) {
                        $dskapi_vnoski_visible_arr[40] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 274877906944) {
                    $dskapi_vnoski_visible_arr[41] = true;
                } else {
                    $dskapi_vnoski_visible_arr[41] = false;
                    if ($dskapi_vnoski == 41) {
                        $dskapi_vnoski_visible_arr[41] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 549755813888) {
                    $dskapi_vnoski_visible_arr[42] = true;
                } else {
                    $dskapi_vnoski_visible_arr[42] = false;
                    if ($dskapi_vnoski == 42) {
                        $dskapi_vnoski_visible_arr[42] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 1099511627776) {
                    $dskapi_vnoski_visible_arr[43] = true;
                } else {
                    $dskapi_vnoski_visible_arr[43] = false;
                    if ($dskapi_vnoski == 43) {
                        $dskapi_vnoski_visible_arr[43] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 2199023255552) {
                    $dskapi_vnoski_visible_arr[44] = true;
                } else {
                    $dskapi_vnoski_visible_arr[44] = false;
                    if ($dskapi_vnoski == 44) {
                        $dskapi_vnoski_visible_arr[44] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 4398046511104) {
                    $dskapi_vnoski_visible_arr[45] = true;
                } else {
                    $dskapi_vnoski_visible_arr[45] = false;
                    if ($dskapi_vnoski == 45) {
                        $dskapi_vnoski_visible_arr[45] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 8796093022208) {
                    $dskapi_vnoski_visible_arr[46] = true;
                } else {
                    $dskapi_vnoski_visible_arr[46] = false;
                    if ($dskapi_vnoski == 46) {
                        $dskapi_vnoski_visible_arr[46] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 17592186044416) {
                    $dskapi_vnoski_visible_arr[47] = true;
                } else {
                    $dskapi_vnoski_visible_arr[47] = false;
                    if ($dskapi_vnoski == 47) {
                        $dskapi_vnoski_visible_arr[47] = true;
                    }
                }
                if ($dskapi_vnoski_visible & 35184372088832) {
                    $dskapi_vnoski_visible_arr[48] = true;
                } else {
                    $dskapi_vnoski_visible_arr[48] = false;
                    if ($dskapi_vnoski == 48) {
                        $dskapi_vnoski_visible_arr[48] = true;
                    }
                }

                $useragent = array_key_exists('HTTP_USER_AGENT', $_SERVER) ? $_SERVER['HTTP_USER_AGENT'] : '';
                $dskapi_is_mobile = preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4));
                if ($dskapi_is_mobile) {
                    $dskapi_PopUp_Detailed_v1 = "dskapim_PopUp_Detailed_v1";
                    $dskapi_Mask = "dskapim_Mask";
                    $dskapi_picture = DSKAPI_LIVEURL . '/calculators/assets/img/dskm' . $paramsdskapi['dsk_reklama'] . '.png';
                    $dskapi_product_name = "dskapim_product_name";
                    $dskapi_body_panel_txt3 = "dskapim_body_panel_txt3";
                    $dskapi_body_panel_txt4 = "dskapim_body_panel_txt4";
                    $dskapi_body_panel_txt3_left = "dskapim_body_panel_txt3_left";
                    $dskapi_body_panel_txt3_right = "dskapim_body_panel_txt3_right";
                    $dskapi_sumi_panel = "dskapim_sumi_panel";
                    $dskapi_kredit_panel = "dskapim_kredit_panel";
                    $dskapi_body_panel_footer = "dskapim_body_panel_footer";
                    $dskapi_body_panel_left = "dskapim_body_panel_left";
                } else {
                    $dskapi_PopUp_Detailed_v1 = "dskapi_PopUp_Detailed_v1";
                    $dskapi_Mask = "dskapi_Mask";
                    $dskapi_picture = DSKAPI_LIVEURL . '/calculators/assets/img/dsk' . $paramsdskapi['dsk_reklama'] . '.png';
                    $dskapi_product_name = "dskapi_product_name";
                    $dskapi_body_panel_txt3 = "dskapi_body_panel_txt3";
                    $dskapi_body_panel_txt4 = "dskapi_body_panel_txt4";
                    $dskapi_body_panel_txt3_left = "dskapi_body_panel_txt3_left";
                    $dskapi_body_panel_txt3_right = "dskapi_body_panel_txt3_right";
                    $dskapi_sumi_panel = "dskapi_sumi_panel";
                    $dskapi_kredit_panel = "dskapi_kredit_panel";
                    $dskapi_body_panel_footer = "dskapi_body_panel_footer";
                    $dskapi_body_panel_left = "dskapi_body_panel_left";
                }

                if (($dskapi_price != 0) && ($dskapi_options) && $dskapi_is_visible && ($paramsdskapi['dsk_status'] == 1) && ($dskapi_button_status != 0)) {
                    $this->context->smarty->assign(
                        array(
                            'dskapi_zaglavie' => $dskapi_zaglavie,
                            'dskapi_custom_button_status' => $dskapi_custom_button_status,
                            'dskapi_button_normal_custom' => $dskapi_button_normal_custom,
                            'dskapi_button_hover_custom' => $dskapi_button_hover_custom,
                            'dskapi_button_normal' => $dskapi_button_normal,
                            'dskapi_button_hover' => $dskapi_button_hover,
                            'dskapi_isvnoska' => $dskapi_isvnoska,
                            'dskapi_vnoski' => $dskapi_vnoski,
                            'dskapi_vnoska' => number_format($dskapi_vnoska, 2, ".", ""),
                            'dskapi_price' => number_format($dskapi_price, 2, ".", ""),
                            'dskapi_cid' => $dskapi_cid,
                            'dskapi_product_id' => $dskapi_product_id,
                            'DSKAPI_LIVEURL' => DSKAPI_LIVEURL,
                            'dskapi_button_status' => $dskapi_button_status,
                            'dskapi_maxstojnost' => $dskapi_maxstojnost,
                            'dskapi_PopUp_Detailed_v1' => $dskapi_PopUp_Detailed_v1,
                            'dskapi_Mask' => $dskapi_Mask,
                            'dskapi_picture' => $dskapi_picture,
                            'dskapi_product_name' => $dskapi_product_name,
                            'dskapi_body_panel_txt3' => $dskapi_body_panel_txt3,
                            'dskapi_body_panel_txt4' => $dskapi_body_panel_txt4,
                            'dskapi_body_panel_txt3_left' => $dskapi_body_panel_txt3_left,
                            'dskapi_minstojnost' => $dskapi_minstojnost,
                            'dskapi_body_panel_txt3_right' => $dskapi_body_panel_txt3_right,
                            'dskapi_vnoski_visible_arr' => $dskapi_vnoski_visible_arr,
                            'dskapi_sumi_panel' => $dskapi_sumi_panel,
                            'dskapi_kredit_panel' => $dskapi_kredit_panel,
                            'dskapi_body_panel_footer' => $dskapi_body_panel_footer,
                            'dskapi_body_panel_left' => $dskapi_body_panel_left,
                            'DSKAPI_VERSION' => Module::getInstanceByName('dskpayment')->version,
                            'dskapi_sign' => $dskapi_sign,
                            'dskapi_currency_code' => $dskapi_currency_code,
                            'dskapi_eur' => $dskapi_eur,
                            'dskapi_gap' => $dskapi_gap
                        )
                    );
                    return $this->display(__FILE__, 'dskpapiayment.tpl');
                } else {
                    return null;
                }
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    public function hookDisplayLeftColumnProduct($params)
    {
        return $this->hookDisplayRightColumnProduct($params);
    }

    public function hookDisplayReassurance($params)
    {
        return $this->hookDisplayRightColumnProduct($params);
    }
}
