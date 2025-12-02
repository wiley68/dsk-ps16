<?php

/**
 * Intelephense stub file for PrestaShop core classes
 * This file helps the IDE understand PrestaShop core classes
 */

// PrestaShop constants
if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}
if (!defined('_MYSQL_ENGINE_')) {
    define('_MYSQL_ENGINE_', 'InnoDB');
}
if (!defined('_PS_MODULE_DIR_')) {
    define('_PS_MODULE_DIR_', '/modules/');
}
if (!defined('__PS_BASE_URI__')) {
    define('__PS_BASE_URI__', '/');
}

/**
 * pSQL is a PrestaShop helper function for SQL escaping
 * @param string $string
 * @param bool $html_ok
 * @return string
 */
if (!function_exists('pSQL')) {
    function pSQL($string, $html_ok = false)
    {
        return '';
    }
}

/**
 * Shop is a PrestaShop core class
 * Located in: classes/shop/Shop.php
 */
class Shop
{
    const CONTEXT_ALL = 1;
    const CONTEXT_GROUP = 2;
    const CONTEXT_SHOP = 4;

    /**
     * @return bool
     */
    public static function isFeatureActive() {}

    /**
     * @param int $context
     * @return void
     */
    public static function setContext($context) {}
}

/**
 * Configuration is a PrestaShop core class for managing configuration values
 * Located in: classes/Configuration.php
 */
class Configuration
{
    /**
     * @param string $key
     * @param int|null $id_lang
     * @param int|null $id_shop_group
     * @param int|null $id_shop
     * @return mixed
     */
    public static function get($key, $id_lang = null, $id_shop_group = null, $id_shop = null) {}

    /**
     * @param string $key
     * @param mixed $values
     * @param bool $html
     * @param int|null $id_shop_group
     * @param int|null $id_shop
     * @return bool
     */
    public static function updateValue($key, $values, $html = false, $id_shop_group = null, $id_shop = null) {}

    /**
     * @param string $key
     * @return bool
     */
    public static function deleteByName($key) {}
}

/**
 * DbQuery is a PrestaShop core class for building database queries
 * Located in: classes/db/DbQuery.php
 */
class DbQuery
{
    /**
     * @param string $fields
     * @return self
     */
    public function select($fields) {}

    /**
     * @param string $table
     * @return self
     */
    public function from($table) {}

    /**
     * @param string $condition
     * @return self
     */
    public function where($condition) {}

    /**
     * @param string $order
     * @return self
     */
    public function orderBy($order) {}
}

/**
 * Db is a PrestaShop core class for database operations
 * Located in: classes/db/Db.php
 */
class Db
{
    /**
     * @return self
     */
    public static function getInstance()
    {
        return new self();
    }

    /**
     * @param string $sql
     * @return bool
     */
    public function execute($sql)
    {
        return false;
    }

    /**
     * @param DbQuery|string $sql
     * @return string|false
     */
    public function getValue($sql)
    {
        return false;
    }
}

/**
 * Validate is a PrestaShop core class for validation
 * Located in: classes/Validate.php
 */
class Validate
{
    /**
     * @param mixed $object
     * @return bool
     */
    public static function isLoadedObject($object)
    {
        return false;
    }
}

/**
 * OrderState is a PrestaShop core class for order states
 * Located in: classes/order/OrderState.php
 */
class OrderState
{
    public $id;
    public $color;
    public $logable;
    public $invoice;
    public $send_email;
    public $hidden;
    public $unremovable;
    public $active;
    public $module_name;
    /** @var array */
    public $name;

    /**
     * @param int|null $id
     */
    public function __construct($id = null) {}

    /**
     * @param int $id
     * @param string $table
     * @return bool
     */
    public static function existsInDatabase($id, $table)
    {
        return false;
    }

    /**
     * @return bool
     */
    public function delete()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function update()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function add()
    {
        return false;
    }
}

/**
 * Language is a PrestaShop core class for languages
 * Located in: classes/Language.php
 */
class Language
{
    /**
     * @param bool $active
     * @return array Array of language arrays with keys like 'id_lang', 'name', etc.
     */
    public static function getLanguages($active = true)
    {
        return array(array('id_lang' => 1));
    }
}

/**
 * Tools is a PrestaShop utility class
 * Located in: classes/Tools.php
 */
class Tools
{
    /**
     * Check if a form has been submitted
     * @param string $key
     * @return bool
     */
    public static function isSubmit($key)
    {
        return false;
    }

    /**
     * Get a value from $_GET or $_POST
     * @param string $key
     * @param mixed $default_value
     * @return mixed
     */
    public static function getValue($key, $default_value = false)
    {
        return $default_value;
    }

    /**
     * Get admin token for a controller
     * @param string $controller
     * @return string
     */
    public static function getAdminTokenLite($controller)
    {
        return '';
    }

    /**
     * Add a JavaScript file
     * @param string $js_uri
     * @param string|null $css_media_type
     * @return void
     */
    public static function addJS($js_uri, $css_media_type = null) {}

    /**
     * Add a CSS file
     * @param string $css_uri
     * @param string|null $css_media_type
     * @return void
     */
    public static function addCSS($css_uri, $css_media_type = null) {}

    /**
     * Get shop domain with SSL
     * @param bool $http
     * @param bool $entities
     * @return string
     */
    public static function getShopDomainSsl($http = false, $entities = false)
    {
        return '';
    }
}

/**
 * HelperForm is a PrestaShop helper class for generating admin forms
 * Located in: classes/helper/HelperForm.php
 */
class HelperForm
{
    /** @var Module */
    public $module;
    /** @var string */
    public $name_controller;
    /** @var string */
    public $token;
    /** @var string */
    public $currentIndex;
    /** @var int */
    public $default_form_language;
    /** @var int */
    public $allow_employee_form_lang;
    /** @var string */
    public $title;
    /** @var bool */
    public $show_toolbar;
    /** @var bool */
    public $toolbar_scroll;
    /** @var string */
    public $submit_action;
    /** @var array */
    public $toolbar_btn;
    /** @var array */
    public $fields_value;

    /**
     * Generate form HTML
     * @param array $fields_form
     * @return string HTML output
     */
    public function generateForm($fields_form)
    {
        return '';
    }
}

/**
 * AdminController is a PrestaShop base class for admin controllers
 * Located in: classes/controller/AdminController.php
 */
class AdminController
{
    /** @var string */
    public static $currentIndex;
}

/**
 * ProductController is a PrestaShop front controller for product pages
 * Located in: controllers/front/ProductController.php
 */
class ProductController {}

/**
 * Currency is a PrestaShop core class for currencies
 * Located in: classes/Currency.php
 */
class Currency
{
    /** @var int */
    public $id;

    /**
     * @param int|null $id
     */
    public function __construct($id = null) {}
}

/**
 * Product is a PrestaShop core class for products
 * Located in: classes/Product.php
 */
class Product
{
    /**
     * @param int|null $id
     */
    public function __construct($id = null) {}

    /**
     * Get product price statically
     * @param int $id_product
     * @param bool $usetax
     * @param int|null $id_product_attribute
     * @param int $decimals
     * @param int|null $divisor
     * @param bool $only_reduc
     * @param bool $usereduc
     * @param int $quantity
     * @param bool $force_associated_tax
     * @param int|null $id_customer
     * @param int|null $id_cart
     * @param int|null $id_address
     * @param mixed $specific_price_output
     * @param bool $with_ecotax
     * @param bool $use_group_reduction
     * @param Context|null $context
     * @param bool $use_customer_price
     * @return float
     */
    public static function getPriceStatic(
        $id_product,
        $usetax = true,
        $id_product_attribute = null,
        $decimals = 6,
        $divisor = null,
        $only_reduc = false,
        $usereduc = true,
        $quantity = 1,
        $force_associated_tax = false,
        $id_customer = null,
        $id_cart = null,
        $id_address = null,
        $specific_price_output = null,
        $with_ecotax = true,
        $use_group_reduction = true,
        $context = null,
        $use_customer_price = true
    ) {
        return 0.0;
    }
}

/**
 * Media is a PrestaShop utility class for media files
 * Located in: classes/Media.php
 */
class Media
{
    /**
     * Get media path
     * @param string $path
     * @return string
     */
    public static function getMediaPath($path)
    {
        return '';
    }
}

/**
 * Module is the base class for all PrestaShop modules
 * Located in: classes/module/Module.php
 */
class Module
{
    public $name;
    public $tab;
    public $version;
    public $author;
    public $ps_versions_compliancy;
    public $bootstrap;
    public $displayName;
    public $description;
    public $confirmUninstall;
    public $warning;
    public $active;
    public $context;
    public $_path;
    public $smarty;

    public function __construct() {}
    public function install() {}
    public function uninstall() {}
    /**
     * Translate a string
     * @param string $string String to translate
     * @param bool|string $specific Specific file name or false
     * @return string Translated string
     */
    public function l($string, $specific = false)
    {
        return '';
    }
    public function registerHook($hook_name) {}
    public function display($file, $template) {}
    /**
     * Display a confirmation message
     * @param string $message
     * @return string HTML output
     */
    public function displayConfirmation($message)
    {
        return '';
    }
    /**
     * Get currencies for a payment module
     * @param int $id_currency
     * @return array|false Array of currencies or false
     */
    public function getCurrency($id_currency)
    {
        return false;
    }

    /**
     * Get module instance by name
     * @param string $module_name
     * @return Module|null Module instance or null if not found
     */
    public static function getInstanceByName($module_name)
    {
        return null;
    }
}

/**
 * PaymentModule is an abstract class that extends Module
 * Located in: classes/payment/PaymentModule.php
 */
abstract class PaymentModule extends Module
{
    /**
     * @return bool
     */
    public function checkCurrency($cart)
    {
        return false;
    }

    /**
     * @param int $cart_id
     * @param int $order_state
     * @param float $amount_paid
     * @param string $payment_method
     * @param string|null $message
     * @param array $extra_vars
     * @param int|null $currency_special
     * @param bool $dont_touch_amount
     * @param bool $secure_key
     * @param Shop|null $shop
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    public function validateOrder(
        $cart_id,
        $order_state,
        $amount_paid,
        $payment_method = '',
        $message = null,
        $extra_vars = array(),
        $currency_special = null,
        $dont_touch_amount = false,
        $secure_key = false,
        ?Shop $shop = null
    ) {
        return false;
    }
}
