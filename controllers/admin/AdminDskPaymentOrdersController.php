<?php

/**
 * Admin Controller for DSK Payment Orders Management
 *
 * This controller provides an administrative interface for viewing and managing
 * DSK Bank credit payment orders. It displays a list of all orders processed
 * through the DSK payment module with their current status.
 *
 * @File: AdminDskPaymentOrdersController.php
 * @Author: Ilko Ivanov
 * @Publisher: Avalon Ltd
 * @Owner: Банка ДСК
 * @Version: 1.2.0
 *
 * @property bool $bootstrap Enable Bootstrap styling
 * @property string $table Database table name
 * @property string $identifier Primary key field name
 * @property string $className Associated model class name
 * @property bool $lang Multi-language support flag
 * @property bool $explicitSelect Use explicit SELECT in queries
 * @property bool $allow_export Allow data export functionality
 * @property bool $deleted Show deleted records flag
 * @property Context $context PrestaShop context object
 * @property string $_select Additional SELECT fields for query
 * @property string $_join Additional JOIN clauses for query
 * @property string $_orderBy Default ORDER BY field
 * @property string $_orderWay Default sort direction (ASC/DESC)
 * @property array $fields_list Column definitions for the list view
 * @property array $page_header_toolbar_btn Toolbar buttons configuration
 *
 * @method void addRowAction(string $action) Add row action button
 * @method string renderList() Render the list view
 * @method void initPageHeaderToolbar() Initialize page header toolbar
 * @method string l(string $string, string|bool $specific = false) Translate string
 */
class AdminDskPaymentOrdersController extends ModuleAdminController
{
    /**
     * Controller constructor
     *
     * Initializes the admin controller with table configuration, column definitions,
     * and SQL query settings for displaying DSK payment orders.
     */
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'dskpayment_orders';
        $this->identifier = 'id';
        $this->className = 'DskPaymentOrder';
        $this->lang = false;
        $this->addRowAction('view');
        $this->explicitSelect = true;
        $this->allow_export = true;
        $this->deleted = false;
        $this->context = Context::getContext();

        // Additional fields from related tables
        $this->_select = '
            o.id_order,
            o.reference,
            o.total_paid_tax_incl,
            o.date_add as order_date,
            CONCAT(c.firstname, " ", c.lastname) as customer_name,
            osl.name as order_status_name
        ';

        // Join with orders, customers and order states
        $this->_join = '
            INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON (o.id_order = a.order_id)
            LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (c.id_customer = o.id_customer)
            LEFT JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl ON (osl.id_order_state = o.current_state AND osl.id_lang = ' . (int) $this->context->language->id . ')
        ';

        // Default sorting by ID descending (newest first)
        $this->_orderBy = 'a.id';
        $this->_orderWay = 'DESC';

        // Column definitions for the list view
        $this->fields_list = array(
            'id' => array(
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs'
            ),
            'order_id' => array(
                'title' => $this->l('ID Поръчка'),
                'align' => 'center',
                'class' => 'fixed-width-sm'
            ),
            'reference' => array(
                'title' => $this->l('Референция'),
                'align' => 'center'
            ),
            'customer_name' => array(
                'title' => $this->l('Клиент'),
                'havingFilter' => true
            ),
            'total_paid_tax_incl' => array(
                'title' => $this->l('Сума'),
                'align' => 'text-right',
                'type' => 'price',
                'currency' => true
            ),
            'order_status' => array(
                'title' => $this->l('DSK Статус'),
                'align' => 'center',
                'callback' => 'getDskStatusBadge',
                'orderby' => true,
                'search' => true,
                'type' => 'select',
                'list' => $this->getDskStatusList(),
                'filter_key' => 'a!order_status'
            ),
            'order_status_name' => array(
                'title' => $this->l('Статус поръчка'),
                'align' => 'center',
                'havingFilter' => true
            ),
            'order_date' => array(
                'title' => $this->l('Дата'),
                'align' => 'center',
                'type' => 'datetime',
                'filter_key' => 'o!date_add'
            )
        );

        parent::__construct();
    }

    /**
     * Get list of DSK Bank order statuses for filtering
     *
     * Returns an associative array mapping status codes (0-8) to their
     * human-readable labels in Bulgarian.
     *
     * Status codes:
     * - 0: Application created
     * - 1: Financial scheme selected
     * - 2: Application completed
     * - 3: Sent to bank
     * - 4: Contact unsuccessful
     * - 5: Cancelled
     * - 6: Rejected
     * - 7: Contract signed
     * - 8: Credit utilized
     *
     * @return array Associative array of status code => label
     */
    private function getDskStatusList()
    {
        return array(
            0 => $this->l('Създадена Апликация'),
            1 => $this->l('Избрана финансова схема'),
            2 => $this->l('Попълнена Апликация'),
            3 => $this->l('Изпратен Банка'),
            4 => $this->l('Неуспешен контакт'),
            5 => $this->l('Анулирана'),
            6 => $this->l('Отказана'),
            7 => $this->l('Подписан договор'),
            8 => $this->l('Усвоен кредит')
        );
    }

    /**
     * Callback method to render DSK status as a colored badge
     *
     * This method is called by PrestaShop's list rendering system
     * to format the order_status column with a Bootstrap label badge.
     *
     * @param int $status The DSK order status code (0-8)
     * @param array $row The full row data from the database query
     *
     * @return string HTML string containing the styled badge element
     */
    public function getDskStatusBadge($status, $row)
    {
        $labels = $this->getDskStatusList();
        $label = isset($labels[$status]) ? $labels[$status] : $this->l('Неизвестен');

        $badgeClass = $this->getDskStatusBadgeClass((int) $status);

        return '<span class="label ' . $badgeClass . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    /**
     * Get Bootstrap label CSS class based on status code
     *
     * Maps DSK order status codes to appropriate Bootstrap label classes
     * for visual distinction:
     * - Info (blue): Initial stages (0, 1, 2)
     * - Warning (orange): Sent to bank (3)
     * - Danger (red): Failed states (4, 5, 6)
     * - Primary (dark blue): Contract signed (7)
     * - Success (green): Credit utilized (8)
     *
     * @param int $status The DSK order status code (0-8)
     *
     * @return string Bootstrap label class name
     */
    private function getDskStatusBadgeClass($status)
    {
        switch ($status) {
            case 0:
            case 1:
            case 2:
                return 'label-info';
            case 3:
                return 'label-warning';
            case 4:
            case 5:
            case 6:
                return 'label-danger';
            case 7:
                return 'label-primary';
            case 8:
                return 'label-success';
            default:
                return 'label-default';
        }
    }

    /**
     * Generate the "View" action link for a row
     *
     * Creates a button that links to the full PrestaShop order details page.
     * This allows administrators to quickly navigate to the complete order
     * information from the DSK orders list.
     *
     * @param string $token Security token for admin links
     * @param int $id The DSK payment order record ID
     * @param string|null $name Optional name parameter (unused)
     *
     * @return string HTML string containing the view button, or empty string if invalid
     */
    public function displayViewLink($token, $id, $name = null)
    {
        // Retrieve the order_id from the DSK payment record
        $dskOrder = new DskPaymentOrder((int) $id);
        if (Validate::isLoadedObject($dskOrder) && $dskOrder->order_id > 0) {
            $orderLink = $this->context->link->getAdminLink('AdminOrders') . '&id_order=' . (int) $dskOrder->order_id . '&vieworder';
            return '<a class="btn btn-default" href="' . $orderLink . '" title="' . $this->l('Виж поръчка') . '"><i class="icon-eye"></i> ' . $this->l('Виж') . '</a>';
        }
        return '';
    }

    /**
     * Initialize page header toolbar buttons
     *
     * Removes the default "Add new" button since DSK payment records
     * should only be created automatically when orders are placed,
     * not manually by administrators.
     *
     * @return void
     */
    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();
        // Remove the "Add new" button - records should not be created manually
        unset($this->page_header_toolbar_btn['new']);
    }

    /**
     * Render the orders list view
     *
     * Adds the view action to each row and delegates to the parent
     * class for actual rendering of the list table.
     *
     * @return string Rendered HTML list
     */
    public function renderList()
    {
        $this->addRowAction('view');
        return parent::renderList();
    }
}
