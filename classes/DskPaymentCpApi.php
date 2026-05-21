<?php

/**
 * Control Panel API endpoints (cache purge, etc.).
 *
 * @File: DskPaymentCpApi.php
 * @Author: Ilko Ivanov
 * @Version: 1.2.2
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/DskPaymentApiCache.php';

class DskPaymentCpApi
{
    const PARAM_CID = 'cid';

    const HEADER_CP_ORIGIN = 'HTTP_X_DSKAPI_CP_ORIGIN';

    /**
     * Handles a CP cache-clear POST request and returns the JSON payload.
     *
     * @return array<string, mixed>
     */
    public static function processClearCacheRequest()
    {
        if (!self::isPostRequest()) {
            return self::buildResponse(
                array(
                    'success' => false,
                    'message' => 'Method not allowed',
                ),
                405
            );
        }

        if (!self::authorizeCpRequest()) {
            return self::buildResponse(
                array(
                    'success' => false,
                    'message' => 'Forbidden',
                ),
                403
            );
        }

        $storedCid = (string) Configuration::get('dskapi_cid');
        $deleted = DskPaymentApiCache::deleteByCid($storedCid);

        return self::buildResponse(
            array(
                'success' => true,
                'deleted' => $deleted,
                'cid' => $storedCid,
            ),
            200
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param int                  $httpStatus
     *
     * @return array<string, mixed>
     */
    private static function buildResponse(array $payload, $httpStatus)
    {
        $payload['_http_status'] = (int) $httpStatus;

        return $payload;
    }

    /**
     * @return bool
     */
    private static function isPostRequest()
    {
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper((string) $_SERVER['REQUEST_METHOD'])
            : '';

        return $method === 'POST';
    }

    /**
     * @return bool
     */
    private static function authorizeCpRequest()
    {
        if (!self::isModuleEnabled()) {
            return false;
        }

        $storedCid = (string) Configuration::get('dskapi_cid');
        if ($storedCid === '') {
            return false;
        }

        $requestCid = (string) Tools::getValue(self::PARAM_CID, '');
        if ($requestCid === '' || !self::hashEquals($storedCid, $requestCid)) {
            return false;
        }

        return self::isRequestFromControlPanel();
    }

    /**
     * @return bool
     */
    private static function isModuleEnabled()
    {
        return (int) Configuration::get('dskapi_status') === 1;
    }

    /**
     * @return bool
     */
    private static function isRequestFromControlPanel()
    {
        $allowedHost = self::getAllowedCpHost();
        if ($allowedHost === '') {
            return false;
        }

        $requestHosts = self::getRequestSourceHosts();
        if ($requestHosts === array()) {
            return false;
        }

        foreach ($requestHosts as $host) {
            if (self::hashEquals($allowedHost, $host)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     */
    private static function getAllowedCpHost()
    {
        $host = parse_url(DSKAPI_LIVEURL, PHP_URL_HOST);
        if (empty($host) || !is_string($host)) {
            return '';
        }

        return strtolower($host);
    }

    /**
     * @return string[]
     */
    private static function getRequestSourceHosts()
    {
        $hosts = array();
        $headerMap = array(
            'HTTP_ORIGIN' => isset($_SERVER['HTTP_ORIGIN']) ? (string) $_SERVER['HTTP_ORIGIN'] : '',
            'HTTP_REFERER' => isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '',
            self::HEADER_CP_ORIGIN => isset($_SERVER[self::HEADER_CP_ORIGIN])
                ? (string) $_SERVER[self::HEADER_CP_ORIGIN]
                : '',
        );

        foreach ($headerMap as $value) {
            if ($value === '') {
                continue;
            }

            $host = parse_url($value, PHP_URL_HOST);
            if (empty($host) || !is_string($host)) {
                $host = preg_replace('/:\d+$/', '', trim($value));
                if ($host === null) {
                    $host = '';
                }
            }

            if ($host !== '') {
                $hosts[] = strtolower($host);
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * Timing-safe string comparison (PHP 5.6+).
     *
     * @param string $known
     * @param string $user
     *
     * @return bool
     */
    private static function hashEquals($known, $user)
    {
        if (function_exists('hash_equals')) {
            return hash_equals($known, $user);
        }

        if (!is_string($known) || !is_string($user)) {
            return false;
        }

        $knownLen = strlen($known);
        $userLen = strlen($user);
        $result = $knownLen ^ $userLen;
        $len = min($knownLen, $userLen);

        for ($i = 0; $i < $len; $i++) {
            $result |= ord($known[$i]) ^ ord($user[$i]);
        }

        return $result === 0;
    }
}
