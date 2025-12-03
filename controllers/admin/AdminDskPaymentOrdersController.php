<?php
/**
 * @File: AdminDskPaymentOrdersController.php
 * @Author: Ilko Ivanov
 * @Publisher: Avalon Ltd
 * @Owner: Банка ДСК
 * @Version: 1.2.0
 *
 * Admin контролер за показване на DSK поръчки
 *
 * @property bool $bootstrap
 * @property string $table
 * @property string $identifier
 * @property string $className
 * @property bool $lang
 * @property bool $explicitSelect
 * @property bool $allow_export
 * @property bool $deleted
 * @property Context $context
 * @property string $_select
 * @property string $_join
 * @property string $_orderBy
 * @property string $_orderWay
 * @property array $fields_list
 * @property array $page_header_toolbar_btn
 * @method void addRowAction(string $action)
 * @method string renderList()
 * @method void initPageHeaderToolbar()
 * @method string l(string $string, string|bool $specific = false)
 */
class AdminDskPaymentOrdersController extends ModuleAdminController
{
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

        $this->_select = '
            o.id_order,
            o.reference,
            o.total_paid_tax_incl,
            o.date_add as order_date,
            CONCAT(c.firstname, " ", c.lastname) as customer_name,
            osl.name as order_status_name
        ';

        $this->_join = '
            INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON (o.id_order = a.order_id)
            LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (c.id_customer = o.id_customer)
            LEFT JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl ON (osl.id_order_state = o.current_state AND osl.id_lang = ' . (int) $this->context->language->id . ')
        ';

        $this->_orderBy = 'a.id';
        $this->_orderWay = 'DESC';

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
     * Връща списък със статуси за филтриране
     *
     * @return array
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
     * Callback за показване на DSK статус като badge
     *
     * @param int $status
     * @param array $row
     * @return string
     */
    public function getDskStatusBadge($status, $row)
    {
        $labels = $this->getDskStatusList();
        $label = isset($labels[$status]) ? $labels[$status] : $this->l('Неизвестен');

        $badgeClass = $this->getDskStatusBadgeClass((int) $status);

        return '<span class="label ' . $badgeClass . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    /**
     * Връща CSS клас за label според статуса
     *
     * @param int $status
     * @return string
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
     * Действие при клик на "Виж"
     *
     * @param string $token
     * @param int $id
     * @param string|null $name
     * @return string
     */
    public function displayViewLink($token, $id, $name = null)
    {
        // Вземаме order_id от записа
        $dskOrder = new DskPaymentOrder((int) $id);
        if (Validate::isLoadedObject($dskOrder) && $dskOrder->order_id > 0) {
            $orderLink = $this->context->link->getAdminLink('AdminOrders') . '&id_order=' . (int) $dskOrder->order_id . '&vieworder';
            return '<a class="btn btn-default" href="' . $orderLink . '" title="' . $this->l('Виж поръчка') . '"><i class="icon-eye"></i> ' . $this->l('Виж') . '</a>';
        }
        return '';
    }

    /**
     * Добавя бутони в toolbar
     */
    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();
        // Премахваме бутона за добавяне - не искаме ръчно да се добавят записи
        unset($this->page_header_toolbar_btn['new']);
    }

    /**
     * Render списъка
     */
    public function renderList()
    {
        $this->addRowAction('view');
        return parent::renderList();
    }
}

