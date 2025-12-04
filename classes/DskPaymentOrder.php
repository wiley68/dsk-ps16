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

/**
 * DSK Payment Order Model
 *
 * This class extends PrestaShop's ObjectModel to manage DSK Bank payment order records.
 * It tracks the status of credit orders submitted to DSK Bank and provides methods
 * for creating, updating, retrieving, and deleting payment order records.
 *
 * Status codes (0-8) represent different stages of the credit application process
 * as defined by DSK Bank's API.
 *
 * @package DskPayment
 * @since 1.2.0
 */
class DskPaymentOrder extends ObjectModel
{
    /**
     * Primary key - auto-increment ID
     *
     * @var int
     */
    public $id;

    /**
     * PrestaShop order ID reference
     *
     * Links this DSK payment record to the corresponding PrestaShop order.
     *
     * @var int
     */
    public $order_id;

    /**
     * DSK Bank order status code
     *
     * Possible values:
     * - 0: Pending / Initial state
     * - 1: Submitted to bank
     * - 2: Under review
     * - 3: Approved
     * - 4: Rejected
     * - 5: Cancelled by customer
     * - 6: Documents required
     * - 7: Completed / Finalized
     * - 8: Error / Failed
     *
     * @var int
     */
    public $order_status;

    /**
     * Record creation timestamp
     *
     * @var string DateTime in 'Y-m-d H:i:s' format
     */
    public $created_at;

    /**
     * Record last update timestamp
     *
     * @var string|null DateTime in 'Y-m-d H:i:s' format, null if never updated
     */
    public $updated_at;

    /**
     * ObjectModel definition for database mapping
     *
     * Defines the table name, primary key, and field specifications
     * for PrestaShop's ORM system.
     *
     * @var array
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'dskpayment_orders',
        'primary' => 'id',
        'fields' => array(
            'order_id' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true),
            'order_status' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true, 'size' => 4),
            'created_at' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true),
            'updated_at' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => false),
        ),
    );

    /**
     * Create or update a DSK payment order record
     *
     * If an order with the given order_id already exists, it will be updated
     * with the new status. Otherwise, a new record will be created.
     *
     * @param int $orderId PrestaShop order ID to associate with this record
     * @param int $orderStatus DSK Bank order status code (0-8)
     *
     * @return DskPaymentOrder|false The created/updated object on success, false on failure
     */
    public static function create($orderId, $orderStatus = 0)
    {
        $orderId = (int) $orderId;
        $orderStatus = (int) $orderStatus;

        // Validate status range
        if ($orderStatus < 0 || $orderStatus > 8) {
            return false;
        }

        // Check if order already exists
        $existingOrder = self::getByOrderId($orderId);

        if ($existingOrder && Validate::isLoadedObject($existingOrder)) {
            // Update existing order
            $existingOrder->order_status = $orderStatus;
            $existingOrder->updated_at = date('Y-m-d H:i:s');

            if ($existingOrder->update()) {
                return $existingOrder;
            }

            return false;
        }

        // Create new order record
        $dskOrder = new self();
        $dskOrder->order_id = $orderId;
        $dskOrder->order_status = $orderStatus;
        $dskOrder->created_at = date('Y-m-d H:i:s');
        $dskOrder->updated_at = null;

        if ($dskOrder->add()) {
            return $dskOrder;
        }

        return false;
    }

    /**
     * Update the status of an existing DSK payment order
     *
     * Finds the record by PrestaShop order ID and updates its status.
     * The updated_at timestamp is automatically set to the current time.
     *
     * @param int $orderId PrestaShop order ID
     * @param int $orderStatus New DSK Bank order status code (0-8)
     *
     * @return bool True on successful update, false if order not found or update failed
     */
    public static function updateStatus($orderId, $orderStatus)
    {
        $orderId = (int) $orderId;
        $orderStatus = (int) $orderStatus;

        // Validate status range
        if ($orderStatus < 0 || $orderStatus > 8) {
            return false;
        }

        // Find existing order
        $dskOrder = self::getByOrderId($orderId);
        if (!$dskOrder || !Validate::isLoadedObject($dskOrder)) {
            return false;
        }

        // Update status and timestamp
        $dskOrder->order_status = $orderStatus;
        $dskOrder->updated_at = date('Y-m-d H:i:s');

        return $dskOrder->update();
    }

    /**
     * Retrieve a DSK payment order by PrestaShop order ID
     *
     * Performs a database lookup to find the DSK payment record
     * associated with the given PrestaShop order.
     *
     * @param int $orderId PrestaShop order ID to search for
     *
     * @return DskPaymentOrder|false The order object if found, false otherwise
     */
    public static function getByOrderId($orderId)
    {
        $orderId = (int) $orderId;

        $sql = new DbQuery();
        $sql->select('id');
        $sql->from('dskpayment_orders');
        $sql->where('order_id = ' . $orderId);

        $id = Db::getInstance()->getValue($sql);

        if ($id) {
            return new self((int) $id);
        }

        return false;
    }

    /**
     * Retrieve a DSK payment order by its primary key ID
     *
     * @param int $id DSK payment order primary key
     * @param int|null $idLang Language ID (unused, kept for interface compatibility)
     *
     * @return DskPaymentOrder|false The order object if found and valid, false otherwise
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
     * Delete a DSK payment order by PrestaShop order ID
     *
     * Finds and removes the DSK payment record associated with
     * the given PrestaShop order.
     *
     * @param int $orderId PrestaShop order ID
     *
     * @return bool True on successful deletion, false if not found or deletion failed
     */
    public static function deleteByOrderId($orderId)
    {
        $orderId = (int) $orderId;

        $dskOrder = self::getByOrderId($orderId);
        if (!$dskOrder || !Validate::isLoadedObject($dskOrder)) {
            return false;
        }

        return $dskOrder->delete();
    }

    /**
     * Delete a DSK payment order by its primary key ID
     *
     * @param int $id DSK payment order primary key
     *
     * @return bool True on successful deletion, false if not found or deletion failed
     */
    public static function deleteById($id)
    {
        $id = (int) $id;

        $dskOrder = self::getById($id);
        if (!$dskOrder || !Validate::isLoadedObject($dskOrder)) {
            return false;
        }

        return $dskOrder->delete();
    }
}
