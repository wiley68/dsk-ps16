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
    public function l($string, $specific = false) {}
    public function registerHook($hook_name) {}
    public function display($file, $template) {}
    public function displayConfirmation($message) {}
    public function getCurrency($id_currency) {}
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
