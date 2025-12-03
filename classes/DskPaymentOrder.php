<?php

/**
 * Model for DSK Payment orders tracking
 *
 * @File: DskPaymentOrder.php
 * @Author: Ilko Ivanov
 * @Author e-mail: ilko.iv@gmail.com
 * @Publisher: Avalon Ltd
 * @Publisher e-mail: home@avalonbg.com
 * @Owner: Банка ДСК
 * @Version: 1.2.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class DskPaymentOrder extends ObjectModel
{
    /** @var int */
    public $id;

    /** @var int Order ID from PrestaShop orders table */
    public $order_id;

    /** @var int Order status (0-8) */
    public $order_status;

    /** @var string Creation date */
    public $created_at;

    /** @var string|null Update date */
    public $updated_at;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'dskpayment_orders',
        'primary' => 'id',
        'fields' => [
            'order_id' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'order_status' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true, 'size' => 4],
            'created_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true],
            'updated_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => false],
        ],
    ];

    /**
     * Create or update a DSK payment order record
     * If order with this order_id exists, it will be updated, otherwise created
     *
     * @param int $orderId PrestaShop order ID
     * @param int $orderStatus Order status (0-8)
     * @return DskPaymentOrder|false Created/updated object or false on failure
     */
    public static function create(int $orderId, int $orderStatus = 0)
    {
        if ($orderStatus < 0 || $orderStatus > 8) {
            return false;
        }

        // Check if order already exists
        $existingOrder = self::getByOrderId($orderId);

        if ($existingOrder && Validate::isLoadedObject($existingOrder)) {
            // Update existing order
            $existingOrder->order_status = (int) $orderStatus;
            $existingOrder->updated_at = date('Y-m-d H:i:s');

            if ($existingOrder->update()) {
                return $existingOrder;
            }

            return false;
        }

        // Create new order
        $dskOrder = new self();
        $dskOrder->order_id = (int) $orderId;
        $dskOrder->order_status = (int) $orderStatus;
        $dskOrder->created_at = date('Y-m-d H:i:s');
        $dskOrder->updated_at = null;

        if ($dskOrder->add()) {
            return $dskOrder;
        }

        return false;
    }

    /**
     * Update order status
     *
     * @param int $orderId PrestaShop order ID
     * @param int $orderStatus New order status (0-8)
     * @return bool True on success, false otherwise
     */
    public static function updateStatus(int $orderId, int $orderStatus): bool
    {
        if ($orderStatus < 0 || $orderStatus > 8) {
            return false;
        }

        $dskOrder = self::getByOrderId($orderId);
        if (!Validate::isLoadedObject($dskOrder)) {
            return false;
        }

        $dskOrder->order_status = (int) $orderStatus;
        $dskOrder->updated_at = date('Y-m-d H:i:s');

        return $dskOrder->update();
    }

    /**
     * Get order by PrestaShop order ID
     *
     * @param int $orderId PrestaShop order ID
     * @return DskPaymentOrder|false Order object or false if not found
     */
    public static function getByOrderId(int $orderId)
    {
        $sql = new DbQuery();
        $sql->select('id');
        $sql->from('dskpayment_orders');
        $sql->where('order_id = ' . (int) $orderId);

        $id = Db::getInstance()->getValue($sql);

        if ($id) {
            return new self((int) $id);
        }

        return false;
    }

    /**
     * Get order by ID
     *
     * @param int $id DSK payment order ID
     * @param int|null $idLang Language ID
     * @return DskPaymentOrder|false Order object or false if not found
     */
    public static function getById($id, $idLang = null)
    {
        if (!Validate::isUnsignedInt($id)) {
            return false;
        }

        $dskOrder = new self((int) $id);
        if (Validate::isLoadedObject($dskOrder)) {
            return $dskOrder;
        }

        return false;
    }

    /**
     * Delete order by PrestaShop order ID
     *
     * @param int $orderId PrestaShop order ID
     * @return bool True on success, false otherwise
     */
    public static function deleteByOrderId(int $orderId): bool
    {
        $dskOrder = self::getByOrderId($orderId);
        if (!Validate::isLoadedObject($dskOrder)) {
            return false;
        }

        return $dskOrder->delete();
    }

    /**
     * Delete order by ID
     *
     * @param int $id DSK payment order ID
     * @return bool True on success, false otherwise
     */
    public static function deleteById(int $id): bool
    {
        $dskOrder = self::getById($id);
        if (!Validate::isLoadedObject($dskOrder)) {
            return false;
        }

        return $dskOrder->delete();
    }
}
