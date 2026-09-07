<?php
/**
 * 2026 GF Experiences
 *
 * The admin inbox for booking enquiries — story 1.12, AC-6.
 *
 * Standard AdminController/HelperList: list, filter and sort come from
 * $fields_list, CSV export from $allow_export, both stock PrestaShop
 * behaviour rather than anything hand-rolled here. The edit screen is where
 * this earns its keep — it shows the full submission read-only (a guest's
 * answers are not something staff should be able to silently rewrite) next
 * to the two fields AC-6 actually asks staff to change: status and
 * assignee.
 *
 * "New" and "Delete" are deliberately not offered: an enquiry is created by
 * a guest, never by staff, and a stray spam entry that slipped past the
 * honeypot and rate limit is data worth keeping for the abuse pattern it
 * shows, not deleting.
 *
 * PRESENTATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

// This extends plain AdminController, not ModuleAdminController, so nothing
// guarantees gfbrand.php — and the GFModuleServices::loadClasses() call
// inside it that requires classes/GfInquiry.php — has already run by the
// time PrestaShop instantiates this controller for the tab. Required
// directly rather than relying on load order.
require_once _PS_MODULE_DIR_ . 'gfbrand/classes/GfInquiry.php';

class AdminGfInquiriesController extends AdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'gf_booking_inquiry';
        $this->className = 'GfInquiry';
        $this->identifier = 'id_gf_booking_inquiry';
        $this->lang = false;
        $this->allow_export = true;

        $this->addRowAction('edit');

        $this->_select = '
            CONCAT(a.`first_name`, \' \', a.`last_name`) AS `full_name`,
            pl.`name` AS `establishment_name`,
            CONCAT(e.`firstname`, \' \', e.`lastname`) AS `employee_name`
        ';
        $this->_join = '
            LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                   ON (pl.`id_product` = a.`id_product` AND pl.`id_lang` = ' . (int) Context::getContext()->language->id . ')
            LEFT JOIN `' . _DB_PREFIX_ . 'employee` e ON (e.`id_employee` = a.`id_employee`)
        ';
        $this->_orderBy = 'date_add';
        $this->_orderWay = 'DESC';

        $this->fields_list = [
            'id_gf_booking_inquiry' => [
                'title' => $this->l('ID'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
            ],
            'full_name' => [
                'title' => $this->l('Name'),
                'havingFilter' => true,
            ],
            'email' => [
                'title' => $this->l('Email'),
            ],
            'dest_country' => [
                'title' => $this->l('Destination'),
            ],
            'establishment_name' => [
                'title' => $this->l('Establishment'),
            ],
            'status' => [
                'title' => $this->l('Status'),
                'type' => 'select',
                'list' => $this->statusLabels(),
                'filter_key' => 'a!status',
                'order_key' => 'status',
            ],
            'employee_name' => [
                'title' => $this->l('Assigned to'),
            ],
            'date_add' => [
                'title' => $this->l('Received'),
                'align' => 'right',
                'type' => 'datetime',
            ],
        ];

        parent::__construct();
    }

    public function initToolbar()
    {
        parent::initToolbar();
        unset($this->toolbar_btn['new']);
    }

    /**
     * @return array<string, string> status value => translated label, for
     *                                 both the list filter and the edit form.
     */
    private function statusLabels()
    {
        return [
            GfInquiry::STATUS_NEW => $this->l('New'),
            GfInquiry::STATUS_IN_PROGRESS => $this->l('In progress'),
            GfInquiry::STATUS_QUOTED => $this->l('Quoted'),
            GfInquiry::STATUS_WON => $this->l('Won'),
            GfInquiry::STATUS_LOST => $this->l('Lost'),
        ];
    }

    /**
     * @return array<int, string> id_employee => full name, for the assignee
     *                              select. Only active back-office staff.
     */
    private function employeeOptions()
    {
        $options = [0 => $this->l('Unassigned')];

        foreach (Employee::getEmployees(true) as $employee) {
            $options[(int) $employee['id_employee']] = $employee['firstname'] . ' ' . $employee['lastname'];
        }

        return $options;
    }

    /**
     * Builds the edit form: the submission, read-only, plus the two fields
     * AC-6 asks staff to actually change. $this->object is already loaded
     * by AdminController::initContent() before it dispatches here.
     */
    public function renderForm()
    {
        /** @var GfInquiry $inquiry */
        $inquiry = $this->object;

        $this->fields_form = [
            'legend' => [
                'title' => $this->l('Enquiry'),
                'icon' => 'icon-envelope',
            ],
            'input' => [
                [
                    'type' => 'html',
                    'name' => 'gf_summary',
                    'label' => $this->l('Submission'),
                    'html_content' => $this->summaryHtml($inquiry),
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Status'),
                    'name' => 'status',
                    'options' => [
                        'query' => $this->optionList($this->statusLabels()),
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Assigned to'),
                    'name' => 'id_employee',
                    'options' => [
                        'query' => $this->optionList($this->employeeOptions()),
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
            ],
        ];

        return parent::renderForm();
    }

    /**
     * @param  array<int|string, string> $labels
     * @return array[] [['id' => key, 'name' => label], ...]
     */
    private function optionList(array $labels)
    {
        $options = [];

        foreach ($labels as $value => $label) {
            $options[] = ['id' => $value, 'name' => $label];
        }

        return $options;
    }

    /**
     * Read-only recap of everything the guest submitted. Deliberately not
     * editable: these are the guest's own words, not a record for staff to
     * rewrite; only status and assignment (AC-6) are.
     *
     * @return string Pre-escaped HTML.
     */
    private function summaryHtml(GfInquiry $inquiry)
    {
        $rows = [
            $this->l('Phone') => $inquiry->phone,
            $this->l('Home country') => $inquiry->home_country,
            $this->l('Home city') => $inquiry->home_city,
            $this->l('Promo code') => $inquiry->promo_code,
            $this->l('Travel date') => $inquiry->travel_date,
            $this->l('Duration') => $inquiry->duration,
            $this->l('Adults') => $inquiry->adults,
            $this->l('Children') => $inquiry->children,
            $this->l('Best time to call') => $inquiry->best_time_call,
            $this->l('Found us via') => $inquiry->referral_source,
            $this->l('Message') => $inquiry->message,
            $this->l('Consent given') => $inquiry->consent_at,
            $this->l('IP address') => $inquiry->ip_address,
        ];

        $html = '<table class="table">';
        foreach ($rows as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $html .= '<tr><th style="width:200px">' . Tools::safeOutput($label) . '</th>'
                . '<td>' . nl2br(Tools::safeOutput((string) $value)) . '</td></tr>';
        }
        $html .= '</table>';

        return $html;
    }
}
