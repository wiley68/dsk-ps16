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

require_once __DIR__ . '/classes/DskPaymentOrder.php';

/**
 * Class Dskpayment
 * @extends PaymentModule
 */
class Dskpayment extends PaymentModule
{

    const HOOKS = [
        'actionFrontControllerSetMedia',
        'payment',
        'displayHome',
        'displayPaymentEU',
        'displayReassurance',
        'displayRightColumnProduct',
        'displayShoppingCart'
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

        // Install admin tab
        if (!$this->installAdminTab()) {
            return false;
        }

        return true;
    }

    /**
     * Инсталира Admin Tab за DSK поръчки
     *
     * @return bool
     */
    private function installAdminTab()
    {
        // Проверка дали таба вече съществува
        $existingTabId = (int) Tab::getIdFromClassName('AdminDskPaymentOrders');
        if ($existingTabId > 0) {
            return true;
        }

        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminDskPaymentOrders';
        $tab->module = $this->name;

        // Намираме ID на родителския таб "Поръчки" (AdminParentOrders)
        $parentTabId = (int) Tab::getIdFromClassName('AdminParentOrders');
        if ($parentTabId <= 0) {
            // Fallback: използваме AdminOrders
            $parentTabId = (int) Tab::getIdFromClassName('AdminOrders');
        }
        $tab->id_parent = $parentTabId > 0 ? $parentTabId : 0;

        // Задаваме име на всички езици
        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            $tab->name[$language['id_lang']] = 'DSK Поръчки';
        }

        return $tab->add();
    }

    /**
     * Деинсталира Admin Tab
     *
     * @return bool
     */
    private function uninstallAdminTab()
    {
        $tabId = (int) Tab::getIdFromClassName('AdminDskPaymentOrders');
        if ($tabId > 0) {
            $tab = new Tab($tabId);
            if (Validate::isLoadedObject($tab)) {
                return $tab->delete();
            }
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
            !$this->uninstallOrderStates() ||
            !$this->uninstallAdminTab()
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
            $dskapi_status = (int)Tools::getValue('dskapi_status');
            $dskapi_cid = (string)Tools::getValue('dskapi_cid');
            $dskapi_reklama = (int)Tools::getValue('dskapi_reklama');
            $dskapi_gap = (int)Tools::getValue('dskapi_gap');

            Configuration::updateValue('dskapi_status', $dskapi_status);
            Configuration::updateValue('dskapi_cid', $dskapi_cid);
            Configuration::updateValue('dskapi_reklama', $dskapi_reklama);
            Configuration::updateValue('dskapi_gap', $dskapi_gap);

            $output .= $this->displayConfirmation($this->l('Промените са успешно запазени'));
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
                    'label'     => 'Банка ДСК покупки на Кредит',
                    'desc'      => 'Дава възможност на Вашите клиенти да закупуват стока на изплащане с Банка ДСК.',
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
                    'desc'      => $this->l('Уникален идентификатор на магазина в системата на Банка ДСК.'),
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
                'desc' => $this->l('Запази'),
                'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                    '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Обратно към списъка')
            )
        );

        // Load current value
        $helper->fields_value['dskapi_status'] = Configuration::get('dskapi_status');
        $helper->fields_value['dskapi_cid'] = Configuration::get('dskapi_cid');
        $helper->fields_value['dskapi_reklama'] = Configuration::get('dskapi_reklama');
        $helper->fields_value['dskapi_gap'] = Configuration::get('dskapi_gap') == "" ? 0 : Configuration::get('dskapi_gap');

        return $helper->generateForm($fields_form);
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
        $dskapi_cid = (string)Configuration::get('dskapi_cid');
        $dskapi_reklama = (int)Configuration::get('dskapi_reklama');
        $dskapi_status = (int)Configuration::get('dskapi_status');

        if ($dskapi_reklama == 0 || $dskapi_status == 0) {
            return '';
        }

        $paramsdskapi = $this->makeApiRequest('/function/getrek.php?cid=' . urlencode($dskapi_cid), 6);
        if ($paramsdskapi === null) {
            return '';
        }

        $dskapi_deviceis = $this->isMobileDevice() ? "mobile" : "pc";

        $dskapi_picture = $paramsdskapi['dsk_picture'];
        $dskapi_container_txt1 = $paramsdskapi['dsk_container_txt1'];
        $dskapi_container_txt2 = $paramsdskapi['dsk_container_txt2'];
        $dskapi_logo_url = $paramsdskapi['dsk_logo_url'];
        $dskapi_logo = Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo.png');

        if (
            (int)$paramsdskapi['dsk_status'] !== 1 ||
            (int)$paramsdskapi['dsk_container_status'] !== 1
        ) {
            return '';
        }

        $this->context->smarty->assign(
            array(
                'dskapi_deviceis' => $dskapi_deviceis,
                'DSKAPI_LIVEURL' => DSKAPI_LIVEURL,
                'dskapi_picture' => $dskapi_picture,
                'dskapi_container_txt1' => $dskapi_container_txt1,
                'dskapi_container_txt2' => $dskapi_container_txt2,
                'dskapi_logo_url' => $dskapi_logo_url,
                'dskapi_logo' => $dskapi_logo
            )
        );

        return $this->display(__FILE__, 'dskapipanel.tpl');
    }

    public function hookPayment($params)
    {
        if (!$this->active)
            return '';

        if (!$this->checkCurrency($params['cart']))
            return '';

        $dskapi_status = (int)Configuration::get('dskapi_status');
        $dskapi_currency_code = $this->context->currency->iso_code;
        if ($dskapi_status == 0 || ($dskapi_currency_code != 'EUR' && $dskapi_currency_code != 'BGN'))
            return '';

        $dskapi_cid = (string)Configuration::get('dskapi_cid');
        $cart = $this->context->cart;
        $dskapi_price = (float) $cart->getOrderTotal(true);

        $paramsdskapi = $this->makeApiRequest('/function/getminmax.php?cid=' . urlencode($dskapi_cid), 6);
        if ($paramsdskapi === null) {
            return '';
        }

        $dskapi_eur = (int)$paramsdskapi['dsk_eur'];
        switch ($dskapi_eur) {
            case 0:
                break;
            case 1:
                if ($dskapi_currency_code == "EUR") {
                    $dskapi_price = (float) number_format($dskapi_price * 1.95583, 2, ".", "");
                }
                break;
            case 2:
                $dskapi_sign = "евро";
                if ($dskapi_currency_code == "BGN") {
                    $dskapi_price = (float) number_format($dskapi_price / 1.95583, 2, ".", "");
                }
                break;
        }
        $dskapi_minstojnost = (float) $paramsdskapi['dsk_minstojnost'];
        $dskapi_maxstojnost = (float) $paramsdskapi['dsk_maxstojnost'];
        $dskapi_min_000 = (float) $paramsdskapi['dsk_min_000'];
        $dskapi_status_cp = $paramsdskapi['dsk_status'];

        $dskapi_purcent = (float) $paramsdskapi['dsk_purcent'];
        $dskapi_vnoski_default = (int) $paramsdskapi['dsk_vnoski_default'];
        if (($dskapi_purcent == 0) && ($dskapi_vnoski_default <= 6)) {
            $dskapi_minstojnost = $dskapi_min_000;
        }

        if (($dskapi_status_cp == 0) || ($dskapi_price < $dskapi_minstojnost) || ($dskapi_price > $dskapi_maxstojnost)) {
            return '';
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
            'cta_text' => $this->l('Банка ДСК покупки на Кредит'),
            'action' => $this->context->link->getModuleLink($this->name, 'validation', array(), true)
        );
        return $payment_options;
    }

    /**
     * Hook actionFrontControllerSetMedia - зарежда скриптове и CSS файлове
     * @param array $params
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        // PrestaShop 1.6.x използва addCSS() и addJS() вместо registerStylesheet() и registerJavascript()
        if (isset($this->context->controller->php_self) && 'index' === $this->context->controller->php_self) {
            $homeCssPath = _PS_MODULE_DIR_ . $this->name . '/css/dskapi_rek.css';
            $homeJsPath = _PS_MODULE_DIR_ . $this->name . '/js/dskapi_rek.js';

            // Зареждане на CSS файл
            // В PrestaShop 1.6.x addCSS() може да не поддържа query strings правилно за CSS файлове
            // Затова използваме версия като част от пътя чрез директория или използваме пълен URL
            if (file_exists($homeCssPath)) {
                $cssVersion = filemtime($homeCssPath);
                // Опит 1: Използване на пълен URL с версия
                $cssUrl = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/css/dskapi_rek.css?v=' . $cssVersion;
                $this->context->controller->addCSS($cssUrl, 'all');
            }

            // Зареждане на JavaScript файл с версиониране чрез filemtime
            if (file_exists($homeJsPath)) {
                $jsVersion = filemtime($homeJsPath);
                $this->context->controller->addJS(
                    $this->_path . 'js/dskapi_rek.js?v=' . $jsVersion,
                    'all'
                );
            }
        }
        // PrestaShop 1.6.x използва addCSS() и addJS() вместо registerStylesheet() и registerJavascript()
        if (isset($this->context->controller->php_self) && 'product' === $this->context->controller->php_self) {
            $productJsPath = _PS_MODULE_DIR_ . $this->name . '/js/dskapi_product.js';
            $productCssPath = _PS_MODULE_DIR_ . $this->name . '/css/dskapi_product.css';

            // Зареждане на JavaScript файл с версиониране чрез filemtime
            if (file_exists($productJsPath)) {
                $jsVersion = filemtime($productJsPath);
                $this->context->controller->addJS(
                    $this->_path . 'js/dskapi_product.js?v=' . $jsVersion,
                    'all'
                );
            }

            // Зареждане на CSS файл с версиониране чрез filemtime
            // В PrestaShop 1.6.x addCSS() изисква пълен URL за правилно версиониране
            if (file_exists($productCssPath)) {
                $cssVersion = filemtime($productCssPath);
                $cssUrl = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/css/dskapi_product.css?v=' . $cssVersion;
                $this->context->controller->addCSS($cssUrl, 'all');
            }
        }
        // PrestaShop 1.6.x използва addCSS() и addJS() вместо registerStylesheet() и registerJavascript()
        // Проверка за страницата с количката: controller=order и step=1 (или без step)
        $step = (int) Tools::getValue('step', 1);
        if (
            isset($this->context->controller->php_self)
            && 'order' === $this->context->controller->php_self
            && $step === 1
        ) {
            $cartJsPath = _PS_MODULE_DIR_ . $this->name . '/js/dskapi_cart.js';
            $cartCssPath = _PS_MODULE_DIR_ . $this->name . '/css/dskapi_cart.css';

            // Зареждане на CSS файл с версиониране чрез filemtime
            // В PrestaShop 1.6.x addCSS() изисква пълен URL за правилно версиониране
            if (file_exists($cartCssPath)) {
                $cssVersion = filemtime($cartCssPath);
                $cssUrl = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/css/dskapi_cart.css?v=' . $cssVersion;
                $this->context->controller->addCSS($cssUrl, 'all');
            }

            // Зареждане на JavaScript файл с версиониране чрез filemtime
            if (file_exists($cartJsPath)) {
                $jsVersion = filemtime($cartJsPath);
                $this->context->controller->addJS(
                    $this->_path . 'js/dskapi_cart.js?v=' . $jsVersion,
                    'all'
                );
            }
        }

        // Зареждане на CSS/JS за страницата за плащане на модула (payment)
        // Проверяваме дали контролерът е от модула dskpayment и е payment контролер
        $controller = $this->context->controller;
        $isModulePaymentController = (
            $controller instanceof ModuleFrontController
            && isset($controller->module)
            && $controller->module instanceof Dskpayment
            && Tools::getValue('module') === $this->name
            && Tools::getValue('controller') === 'payment'
        );

        if ($isModulePaymentController) {
            $paymentJsPath = _PS_MODULE_DIR_ . $this->name . '/js/dskapi_payment.js';
            $paymentCssPath = _PS_MODULE_DIR_ . $this->name . '/css/dskapi_payment.css';

            // Зареждане на CSS файл с версиониране чрез filemtime
            if (file_exists($paymentCssPath)) {
                $cssVersion = filemtime($paymentCssPath);
                $cssUrl = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/css/dskapi_payment.css?v=' . $cssVersion;
                $this->context->controller->addCSS($cssUrl, 'all');
            }

            // Зареждане на JavaScript файл с версиониране чрез filemtime
            if (file_exists($paymentJsPath)) {
                $jsVersion = filemtime($paymentJsPath);
                $this->context->controller->addJS(
                    $this->_path . 'js/dskapi_payment.js?v=' . $jsVersion,
                    'all'
                );
            }
        }
    }

    /**
     * Статична променлива за следене дали бутонът вече е показан
     * @var bool
     */
    private static $widgetDisplayed = false;

    /**
     * Общ метод за показване на бутона в продуктова страница
     * Предотвратява дублиране ако и двата хука са налични
     * 
     * @param array $params
     * @return string
     */
    private function displayProductWidget($params)
    {
        // Проверка дали бутонът вече е показан в този request
        if (self::$widgetDisplayed) {
            return '';
        }

        if ('product' !== $this->context->controller->php_self) {
            return '';
        }

        $dskapi_product_id = (int) Tools::getValue('id_product');
        if ($dskapi_product_id <= 0) {
            return '';
        }

        $dskapi_price = (float) Product::getPriceStatic($dskapi_product_id, true);
        if ($dskapi_price <= 0) {
            return '';
        }

        // Маркираме, че бутонът е показан
        self::$widgetDisplayed = true;

        return $this->renderDskWidget(
            $dskapi_price,
            $dskapi_product_id,
            'views/templates/hook/dskpayment_product.tpl'
        );
    }

    public function hookDisplayReassurance($params)
    {
        return $this->displayProductWidget($params);
    }

    public function hookDisplayRightColumnProduct($params)
    {
        return $this->displayProductWidget($params);
    }

    public function hookDisplayShoppingCart($params)
    {
        if ('order' !== $this->context->controller->php_self) {
            return '';
        }

        $dskapi_price = (float) $this->context->cart->getOrderTotal(true);
        if ($dskapi_price <= 0 || !$this->hasAvailableProductInCart()) {
            return '';
        }

        $dskapi_product_id = $this->resolveCartProductId();

        return $this->renderDskWidget(
            $dskapi_price,
            $dskapi_product_id,
            'views/templates/hook/dskpayment_cart.tpl'
        );
    }

    /**
     * Builds the data needed for rendering the financing widget and returns the rendered template.
     *
     * @param float $price
     * @param int $productId
     * @param string $templatePath
     *
     * @return string
     */
    private function renderDskWidget($price, $productId, $templatePath)
    {
        $dskapi_status = (int) Configuration::get('dskapi_status');
        $dskapi_currency_code = isset($this->context->currency->iso_code) ? $this->context->currency->iso_code : '';
        $dskapi_gap = (int) Configuration::get('dskapi_gap');

        if ($dskapi_status === 0 || !in_array($dskapi_currency_code, ['EUR', 'BGN'], true)) {
            return '';
        }

        $dskapi_cid = (string) Configuration::get('dskapi_cid');
        if (empty($dskapi_cid)) {
            return '';
        }

        $dskapi_price = $price;
        $dskapi_sign = 'лв.';
        $response = $this->makeApiRequest('/function/geteur.php?cid=' . urlencode($dskapi_cid));
        if ($response === null) {
            return '';
        }

        $dskapi_eur = (int) (isset($response['dsk_eur']) ? $response['dsk_eur'] : 0);
        switch ($dskapi_eur) {
            case 1:
                $dskapi_sign = 'лв.';
                if ($dskapi_currency_code === 'EUR') {
                    $dskapi_price = (float) number_format($dskapi_price * 1.95583, 2, '.', '');
                }
                break;
            case 2:
                $dskapi_sign = 'евро';
                if ($dskapi_currency_code === 'BGN') {
                    $dskapi_price = (float) number_format($dskapi_price / 1.95583, 2, '.', '');
                }
                break;
        }

        $apiUrl = '/function/getproduct.php?cid=' . urlencode($dskapi_cid)
            . '&price=' . urlencode((string) $dskapi_price)
            . '&product_id=' . urlencode((string) $productId);
        $paramsdskapi = $this->makeApiRequest($apiUrl);
        if ($paramsdskapi === null) {
            return '';
        }

        if (
            !isset(
                $paramsdskapi['dsk_options'],
                $paramsdskapi['dsk_is_visible'],
                $paramsdskapi['dsk_status'],
                $paramsdskapi['dsk_button_status'],
                $paramsdskapi['dsk_reklama']
            )
        ) {
            return '';
        }

        $dskapi_options = (bool) $paramsdskapi['dsk_options'];
        $dskapi_is_visible = (bool) $paramsdskapi['dsk_is_visible'];
        $dskapi_button_status = (int) $paramsdskapi['dsk_button_status'];

        if (
            $dskapi_price <= 0 ||
            !$dskapi_options ||
            !$dskapi_is_visible ||
            (int) $paramsdskapi['dsk_status'] !== 1 ||
            $dskapi_button_status === 0
        ) {
            return '';
        }

        $dskapi_vnoski_visible = (int) (isset($paramsdskapi['dsk_vnoski_visible']) ? $paramsdskapi['dsk_vnoski_visible'] : 0);
        $defaultVnoski = (int) (isset($paramsdskapi['dsk_vnoski_default']) ? $paramsdskapi['dsk_vnoski_default'] : 0);
        $dskapi_vnoski_visible_arr = [];
        for ($vnoska = 3; $vnoska <= 48; $vnoska++) {
            $bitPosition = $vnoska - 3;
            $bitMask = 1 << $bitPosition;
            $dskapi_vnoski_visible_arr[$vnoska] = ($dskapi_vnoski_visible & $bitMask) !== 0 || $defaultVnoski === $vnoska;
        }

        $dskapi_is_mobile = $this->isMobileDevice();
        $prefix = $dskapi_is_mobile ? 'dskapim' : 'dskapi';
        $imgPrefix = $dskapi_is_mobile ? 'dskm' : 'dsk';

        $link = new Link();
        $languageId = isset($this->context->language->id) ? (int) $this->context->language->id : (int) Configuration::get('PS_LANG_DEFAULT');
        $checkoutUrl = $link->getPageLink('order', true, $languageId);

        $this->context->smarty->assign([
            'dskapi_zaglavie' => isset($paramsdskapi['dsk_zaglavie']) ? $paramsdskapi['dsk_zaglavie'] : '',
            'dskapi_custom_button_status' => (int) (isset($paramsdskapi['dsk_custom_button_status']) ? $paramsdskapi['dsk_custom_button_status'] : 0),
            'dskapi_button_normal_custom' => DSKAPI_LIVEURL . '/calculators/assets/img/custom_buttons/' . urlencode($dskapi_cid) . '.png',
            'dskapi_button_hover_custom' => DSKAPI_LIVEURL . '/calculators/assets/img/custom_buttons/' . urlencode($dskapi_cid) . '_hover.png',
            'dskapi_button_normal' => DSKAPI_LIVEURL . '/calculators/assets/img/buttons/dsk.png',
            'dskapi_button_hover' => DSKAPI_LIVEURL . '/calculators/assets/img/buttons/dsk-hover.png',
            'dskapi_isvnoska' => (int) (isset($paramsdskapi['dsk_isvnoska']) ? $paramsdskapi['dsk_isvnoska'] : 0),
            'dskapi_vnoski' => $defaultVnoski,
            'dskapi_vnoska' => number_format((float) (isset($paramsdskapi['dsk_vnoska']) ? $paramsdskapi['dsk_vnoska'] : 0), 2, '.', ''),
            'dskapi_price' => number_format($dskapi_price, 2, '.', ''),
            'dskapi_cid' => $dskapi_cid,
            'dskapi_product_id' => $productId,
            'DSKAPI_LIVEURL' => DSKAPI_LIVEURL,
            'dskapi_button_status' => $dskapi_button_status,
            'dskapi_maxstojnost' => (float) number_format((float) (isset($paramsdskapi['dsk_maxstojnost']) ? $paramsdskapi['dsk_maxstojnost'] : 0), 2, '.', ''),
            'dskapi_minstojnost' => (float) number_format((float) (isset($paramsdskapi['dsk_minstojnost']) ? $paramsdskapi['dsk_minstojnost'] : 0), 2, '.', ''),
            'dskapi_PopUp_Detailed_v1' => $prefix . '_PopUp_Detailed_v1',
            'dskapi_Mask' => $prefix . '_Mask',
            'dskapi_picture' => DSKAPI_LIVEURL . '/calculators/assets/img/' . $imgPrefix . (isset($paramsdskapi['dsk_reklama']) ? $paramsdskapi['dsk_reklama'] : 0) . '.png',
            'dskapi_product_name' => $prefix . '_product_name',
            'dskapi_body_panel_txt3' => $prefix . '_body_panel_txt3',
            'dskapi_body_panel_txt4' => $prefix . '_body_panel_txt4',
            'dskapi_body_panel_txt3_left' => $prefix . '_body_panel_txt3_left',
            'dskapi_body_panel_txt3_right' => $prefix . '_body_panel_txt3_right',
            'dskapi_sumi_panel' => $prefix . '_sumi_panel',
            'dskapi_kredit_panel' => $prefix . '_kredit_panel',
            'dskapi_body_panel_footer' => $prefix . '_body_panel_footer',
            'dskapi_body_panel_left' => $prefix . '_body_panel_left',
            'dskapi_vnoski_visible_arr' => $dskapi_vnoski_visible_arr,
            'DSKAPI_VERSION' => $this->version,
            'dskapi_sign' => $dskapi_sign,
            'dskapi_currency_code' => $dskapi_currency_code,
            'dskapi_eur' => $dskapi_eur,
            'dskapi_gap' => $dskapi_gap,
            'dskapi_checkout_url' => $checkoutUrl
        ]);

        // PrestaShop 1.6.x използва display() вместо fetch()
        // Пътят е относителен спрямо модула, не в 'module:' формат
        return $this->display(__FILE__, $templatePath);
    }


    /**
     * Извършва API заявка и връща декодирания JSON отговор
     *
     * @param string $endpoint API endpoint (без базовия URL)
     * @param int $timeout Timeout в секунди
     * @return array|null Декодираният JSON отговор или null при грешка
     */
    /**
     * Executes an HTTP request to the DSK API and returns the decoded response.
     *
     * @param string $endpoint Relative API endpoint path
     * @param int $timeout Request timeout in seconds
     *
     * @return array|null
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

        if ($response === false || $httpCode !== 200 || !empty($curlError)) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Проверява дали устройството е мобилно
     *
     * @return bool
     */
    /**
     * Detects whether the current visitor uses a mobile device.
     *
     * @return bool
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
     * Checks whether the current cart contains at least one available product.
     *
     * @return bool
     */
    private function hasAvailableProductInCart(): bool
    {
        if (!$this->context->cart instanceof Cart) {
            return false;
        }

        $products = $this->context->cart->getProducts(true);

        if (empty($products)) {
            return false;
        }

        foreach ($products as $product) {
            $quantity = (int) ($product['quantity'] ?? 0);
            $availableForOrder = (bool) ($product['available_for_order'] ?? true);

            if ($quantity > 0 && $availableForOrder) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines the product identifier to send to the API when building the cart widget.
     * If the cart contains exactly one unique product, its ID is returned. Otherwise 0 is used.
     *
     * @return int
     */
    private function resolveCartProductId(): int
    {
        if (!$this->context->cart instanceof Cart) {
            return 0;
        }

        $products = $this->context->cart->getProducts(true);
        if (empty($products)) {
            return 0;
        }

        $uniqueIds = [];
        foreach ($products as $product) {
            $productId = (int) ($product['id_product'] ?? 0);
            if ($productId > 0) {
                $uniqueIds[$productId] = true;
            }
            if (count($uniqueIds) > 1) {
                return 0;
            }
        }

        reset($uniqueIds);
        $firstKey = key($uniqueIds);

        return (int) ($firstKey ?? 0);
    }
}
