<?php

/**
 * DB cache for DSK Control Panel product API responses.
 *
 * @File: DskPaymentApiCache.php
 * @Author: Ilko Ivanov
 * @Version: 1.2.2
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Caches responses from /function/getproduct.php and /function/getproductcustom.php.
 */
class DskPaymentApiCache
{
    const TABLE = 'dskpayment_api_cache';
    const TTL_SECONDS = 900;

    const ENDPOINT_PRODUCT = 'getproduct';
    const ENDPOINT_PRODUCT_CUSTOM = 'getproductcustom';

    /**
     * @var bool
     */
    private static $tableEnsured = false;

    /**
     * Create cache table if missing (idempotent).
     *
     * @return bool
     */
    public static function ensureTable()
    {
        if (self::$tableEnsured) {
            return true;
        }

        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `cache_key` VARCHAR(64) NOT NULL,
            `cid` VARCHAR(64) NOT NULL,
            `product_id` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `price` DECIMAL(12,2) NOT NULL,
            `installments` SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
            `response_json` LONGTEXT NOT NULL,
            `created_at` DATETIME NOT NULL,
            `expires_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `cache_key` (`cache_key`),
            KEY `expires_at` (`expires_at`),
            KEY `cid` (`cid`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        self::$tableEnsured = (bool) Db::getInstance()->execute($sql);

        return self::$tableEnsured;
    }

    /**
     * @param string $endpoint
     * @param string $cid
     * @param int    $productId
     * @param float  $price
     * @param int    $installments
     *
     * @return string
     */
    public static function buildCacheKey($endpoint, $cid, $productId, $price, $installments)
    {
        $normalizedPrice = self::normalizePrice($price);

        return hash(
            'sha256',
            $endpoint . '|' . $cid . '|' . (int) $productId . '|' . $normalizedPrice . '|' . (int) $installments
        );
    }

    /**
     * Fetch product data (cache-first).
     *
     * @param string $cid
     * @param int    $productId
     * @param float  $price
     * @param int    $installments 0 for getproduct.php, >0 for getproductcustom.php
     *
     * @return array|null
     */
    public static function fetchProduct($cid, $productId, $price, $installments = 0)
    {
        $cid = (string) $cid;
        if ($cid === '') {
            return null;
        }

        $productId = (int) $productId;
        $installments = (int) $installments;
        $custom = $installments > 0;
        $endpoint = $custom ? self::ENDPOINT_PRODUCT_CUSTOM : self::ENDPOINT_PRODUCT;
        $cacheKey = self::buildCacheKey($endpoint, $cid, $productId, $price, $installments);

        $cached = self::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $apiPath = self::buildApiPath($custom, $cid, $productId, $price, $installments);
        $response = self::httpGetJson($apiPath);
        if ($response !== null) {
            self::set($cacheKey, $cid, $productId, $price, $installments, $response);
        }

        return $response;
    }

    /**
     * @param string $cacheKey
     *
     * @return array|null
     */
    public static function get($cacheKey)
    {
        if (!self::ensureTable()) {
            return null;
        }

        self::purgeExpired();

        $sql = 'SELECT `response_json`
            FROM `' . _DB_PREFIX_ . self::TABLE . '`
            WHERE `cache_key` = \'' . pSQL($cacheKey) . '\'
            AND `expires_at` > NOW()';

        $row = Db::getInstance()->getRow($sql);
        if (!$row || empty($row['response_json'])) {
            return null;
        }

        $decoded = json_decode($row['response_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param string $cacheKey
     * @param string $cid
     * @param int    $productId
     * @param float  $price
     * @param int    $installments
     * @param array  $response
     *
     * @return bool
     */
    public static function set($cacheKey, $cid, $productId, $price, $installments, array $response)
    {
        if (!self::ensureTable()) {
            return false;
        }

        $json = json_encode($response);
        if ($json === false) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + self::TTL_SECONDS);
        $normalizedPrice = self::normalizePrice($price);

        $sql = 'INSERT INTO `' . _DB_PREFIX_ . self::TABLE . '`
            (`cache_key`, `cid`, `product_id`, `price`, `installments`, `response_json`, `created_at`, `expires_at`)
            VALUES (
                \'' . pSQL($cacheKey) . '\',
                \'' . pSQL($cid) . '\',
                ' . (int) $productId . ',
                ' . (float) $normalizedPrice . ',
                ' . (int) $installments . ',
                \'' . pSQL($json, true) . '\',
                \'' . pSQL($now) . '\',
                \'' . pSQL($expires) . '\'
            )
            ON DUPLICATE KEY UPDATE
                `response_json` = VALUES(`response_json`),
                `created_at` = VALUES(`created_at`),
                `expires_at` = VALUES(`expires_at`)';

        return (bool) Db::getInstance()->execute($sql);
    }

    /**
     * @return bool
     */
    public static function purgeExpired()
    {
        if (!self::ensureTable()) {
            return false;
        }

        return (bool) Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE `expires_at` <= NOW()'
        );
    }

    /**
     * @param bool   $custom
     * @param string $cid
     * @param int    $productId
     * @param float  $price
     * @param int    $installments
     *
     * @return string
     */
    private static function buildApiPath($custom, $cid, $productId, $price, $installments)
    {
        $normalizedPrice = self::normalizePrice($price);

        if ($custom) {
            return '/function/getproductcustom.php?cid=' . urlencode($cid)
                . '&price=' . urlencode($normalizedPrice)
                . '&product_id=' . urlencode((string) (int) $productId)
                . '&dskapi_vnoski=' . urlencode((string) (int) $installments);
        }

        return '/function/getproduct.php?cid=' . urlencode($cid)
            . '&price=' . urlencode($normalizedPrice)
            . '&product_id=' . urlencode((string) (int) $productId);
    }

    /**
     * @param string $endpoint
     * @param int    $timeout
     *
     * @return array|null
     */
    private static function httpGetJson($endpoint, $timeout = 5)
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

        if ($response === false || (int) $httpCode !== 200 || !empty($curlError)) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param float $price
     *
     * @return string
     */
    private static function normalizePrice($price)
    {
        return number_format(round((float) $price, 2), 2, '.', '');
    }
}
