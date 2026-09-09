<?php
/**
 * 2026 GF Experiences
 *
 * The back-office screen for partner organisations — story 1.11, AC-2.
 *
 * Same shape as the advisors screen: create, edit, deactivate, delete,
 * language tabs and drag-reorder. The logo is stored in
 * uploads/establishments/partners/ by file name.
 *
 * PRESENTATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminGfPartnersController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'gf_partner';
        $this->className = 'GfPartner';
        $this->identifier = 'id_gf_partner';
        $this->lang = false;

        $this->_orderBy = 'position';
        $this->_orderWay = 'ASC';

        $this->fields_list = [
            'id_gf_partner' => [
                'title' => $this->l('ID'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
            ],
            'name' => [
                'title' => $this->l('Name'),
                'havingFilter' => true,
            ],
            'category' => [
                'title' => $this->l('Category'),
            ],
            'website_url' => [
                'title' => $this->l('Website'),
            ],
            'position' => [
                'title' => $this->l('Order'),
                'align' => 'text-center',
            ],
            'active' => [
                'title' => $this->l('Active'),
                'type' => 'bool',
                'align' => 'text-center',
            ],
        ];

        $this->addRowAction('edit');
        $this->addRowAction('delete');

        parent::__construct();
    }

    /**
     * Move the uploaded logo into place and put its file name on the object,
     * then hand off to the normal save path.
     */
    public function postProcess()
    {
        $this->applyLogo();

        return parent::postProcess();
    }

    public function renderForm()
    {
        $this->fields_form = [
            'legend' => [
                'title' => $this->object && $this->object->id
                    ? $this->l('Edit partner')
                    : $this->l('New partner'),
                'icon' => 'icon-group',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('Name'),
                    'name' => 'name',
                    'required' => true,
                    'size' => 40,
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->l('Description'),
                    'name' => 'description',
                    'cols' => 20,
                    'rows' => 4,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Website'),
                    'name' => 'website_url',
                    'size' => 60,
                    'validate' => 'isUrl',
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Category'),
                    'name' => 'category',
                    'size' => 30,
                ],
                [
                    'type' => 'image',
                    'label' => $this->l('Logo'),
                    'name' => 'file',
                    'desc' => $this->l('JPG, PNG, GIF or WebP, up to 5 MB. Displayed on white, constrained by height.'),
                    'delete' => true,
                ],
                [
                    'type' => 'switch',
                    'label' => $this->l('Active'),
                    'name' => 'active',
                    'is_bool' => true,
                    'values' => [
                        ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Enabled')],
                        ['id' => 'active_off', 'value' => 0, 'label' => $this->l('Disabled')],
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
            ],
        ];

        /** @var GfPartner $partner */
        $partner = $this->object;

        if ($partner && $partner->id && (string) $partner->logo !== '') {
            $this->fields_value['image'] = $partner->logo;
        }

        return parent::renderForm();
    }

    /**
     * Upload or remove the logo, writing the resulting file name onto the
     * object so the subsequent ObjectModel save persists it.
     */
    private function applyLogo()
    {
        /** @var GfPartner|null $partner */
        $partner = $this->object;

        if (!$partner) {
            return;
        }

        $uploader = new GFAssetUploader('partners');

        if (Tools::isSubmit('gfRemoveImage')) {
            if ((string) $partner->logo !== '') {
                $uploader->remove((string) $partner->logo);
            }

            $partner->logo = null;

            return;
        }

        if ($this->hasUploadedFile('file')) {
            $result = $uploader->upload('file');

            if ($result['success']) {
                $partner->logo = $result['file'];
            } else {
                $this->errors[] = $result['error'];
            }
        }
    }

    /**
     * @param  string $field
     * @return bool
     */
    private function hasUploadedFile($field)
    {
        return isset($_FILES[$field])
            && is_array($_FILES[$field])
            && is_uploaded_file((string) $_FILES[$field]['tmp_name']);
    }
}
