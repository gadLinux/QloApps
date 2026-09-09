<?php
/**
 * 2026 GF Experiences
 *
 * The back-office screen for travel advisors — story 1.11, AC-2.
 *
 * A standard ModuleAdminController: create, edit, deactivate and delete with
 * no developer, plus the language tabs ObjectModel gives a _lang table for
 * free. Position reordering is the stock HelperList drag-and-drop.
 *
 * The photograph is not a Product Image: it is moved into
 * uploads/establishments/advisors/ and stored by file name. The file is
 * handled here, in postProcess(), and its name lands on the object before
 * ObjectModel writes the row.
 *
 * PRESENTATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminGfAdvisorsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'gf_advisor';
        $this->className = 'GfAdvisor';
        $this->identifier = 'id_gf_advisor';
        $this->lang = false;

        $this->_orderBy = 'position';
        $this->_orderWay = 'ASC';

        $this->fields_list = [
            'id_gf_advisor' => [
                'title' => $this->l('ID'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs',
            ],
            'name' => [
                'title' => $this->l('Name'),
                'havingFilter' => true,
            ],
            'phone' => [
                'title' => $this->l('Phone'),
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
     * Move the uploaded photograph into place and put its file name on the
     * object, then hand off to the normal save path.
     */
    public function postProcess()
    {
        $this->applyImage();

        return parent::postProcess();
    }

    public function renderForm()
    {
        $this->fields_form = [
            'legend' => [
                'title' => $this->object && $this->object->id
                    ? $this->l('Edit advisor')
                    : $this->l('New advisor'),
                'icon' => 'icon-user',
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
                    'type' => 'text',
                    'label' => $this->l('Regions served'),
                    'name' => 'regions_served',
                    'size' => 60,
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->l('Bio'),
                    'name' => 'bio',
                    'cols' => 20,
                    'rows' => 5,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Phone (display)'),
                    'name' => 'phone',
                    'size' => 20,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Phone (dial, e.g. +34669771472)'),
                    'name' => 'phone_e164',
                    'size' => 20,
                    'desc' => $this->l('Used for the tel: link on the card.'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Website'),
                    'name' => 'website_url',
                    'size' => 60,
                    'validate' => 'isUrl',
                ],
                [
                    'type' => 'email',
                    'label' => $this->l('Email'),
                    'name' => 'email',
                    'size' => 40,
                ],
                [
                    'type' => 'image',
                    'label' => $this->l('Photograph'),
                    'name' => 'file',
                    'desc' => $this->l('JPG, PNG, GIF or WebP, up to 5 MB.'),
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

        /** @var GfAdvisor $advisor */
        $advisor = $this->object;

        if ($advisor && $advisor->id && (string) $advisor->image !== '') {
            $this->fields_value['image'] = $advisor->image;
        }

        return parent::renderForm();
    }

    /**
     * Upload or remove the photograph, writing the resulting file name onto
     * the object so the subsequent ObjectModel save persists it.
     */
    private function applyImage()
    {
        /** @var GfAdvisor|null $advisor */
        $advisor = $this->object;

        if (!$advisor) {
            return;
        }

        $uploader = new GFAssetUploader('advisors');

        if (Tools::isSubmit('gfRemoveImage')) {
            if ((string) $advisor->image !== '') {
                $uploader->remove((string) $advisor->image);
            }

            $advisor->image = null;

            return;
        }

        if ($this->hasUploadedFile('file')) {
            $result = $uploader->upload('file');

            if ($result['success']) {
                $advisor->image = $result['file'];
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
