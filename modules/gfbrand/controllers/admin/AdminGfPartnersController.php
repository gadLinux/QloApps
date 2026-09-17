<?php
/**
 * 2026 GF Experiences
 *
 * The back-office screen for partner organisations — story 1.11, AC-2.
 *
 * Same shape as the advisors screen: create, edit, deactivate, delete,
 * language tabs (`$this->lang = true` and `'lang' => true` on each translated
 * input — both are required) and drag-reorder. The logo is stored in
 * uploads/establishments/partners/ by file name, applied in copyFromPost()
 * so it runs on the real object for both the add and the edit path.
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
        $this->lang = true;
        $this->position_identifier = 'id_gf_partner';

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
                'position' => 'position',
            ],
            'active' => [
                'title' => $this->l('Active'),
                'active' => 'status',
                'type' => 'bool',
                'align' => 'text-center',
            ],
        ];

        $this->addRowAction('edit');
        $this->addRowAction('delete');

        parent::__construct();
    }

    public function renderForm()
    {
        /** @var GfPartner $partner */
        $partner = $this->object;
        $currentLogo = ($partner && $partner->id && (string) $partner->logo !== '') ? (string) $partner->logo : '';

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
                    'lang' => true,
                    'required' => true,
                    'size' => 40,
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->l('Description'),
                    'name' => 'description',
                    'lang' => true,
                    'cols' => 20,
                    'rows' => 4,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Website'),
                    'name' => 'website_url',
                    'size' => 60,
                    'validate' => 'isUrl',
                    'desc' => $this->l('Full http(s):// address.'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Category'),
                    'name' => 'category',
                    'size' => 30,
                ],
                [
                    'type' => 'file',
                    'label' => $this->l('Logo'),
                    'name' => 'file',
                    'file' => $this->assetFieldHtml('file', 'gfRemoveImage', $currentLogo, 'partners'),
                    'desc' => $this->l('JPG, PNG, GIF or WebP, up to 5 MB. Displayed on white, constrained by height.'),
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

        return parent::renderForm();
    }

    /**
     * Give a newly added partner the next position, so it sorts after every
     * existing row instead of defaulting to 0 and sorting first.
     *
     * @param  GfPartner $object
     * @return bool
     */
    protected function beforeAdd($object)
    {
        if (empty($object->position)) {
            $object->position = (int) Db::getInstance()->getValue(
                'SELECT MAX(`position`) FROM `' . _DB_PREFIX_ . 'gf_partner`'
            ) + 1;
        }

        return true;
    }

    /**
     * Runs on the real object right before add()/update() for both the add
     * and the edit path — the correct hook point for the upload, unlike
     * postProcess() (no object exists yet there when adding).
     *
     * @param GfPartner $object
     * @param string    $table
     */
    protected function copyFromPost(&$object, $table)
    {
        parent::copyFromPost($object, $table);

        if ($table === $this->table) {
            $this->applyLogo($object);
        }
    }

    /**
     * Delete the partner's logo along with the row, so removing a partner
     * does not leave an orphaned file behind.
     */
    public function processDelete()
    {
        $object = $this->loadObject();

        if (Validate::isLoadedObject($object) && (string) $object->logo !== '') {
            (new GFAssetUploader('partners'))->remove((string) $object->logo);
        }

        return parent::processDelete();
    }

    /**
     * Stock HelperList drag-and-drop position update — same shape core uses
     * for Carrier::updatePosition().
     */
    public function ajaxProcessUpdatePositions()
    {
        $way = (int) Tools::getValue('way');
        $id = (int) Tools::getValue('id');
        $positions = Tools::getValue($this->table);

        if (!is_array($positions)) {
            return;
        }

        foreach ($positions as $position => $value) {
            $chunks = explode('_', $value);

            if (isset($chunks[2]) && (int) $chunks[2] === $id) {
                $partner = new GfPartner($id);

                if (Validate::isLoadedObject($partner) && $partner->updatePosition($way, $position)) {
                    echo 'ok position ' . (int) $position . ' for partner ' . $id;
                } else {
                    echo '{"hasError" : true, "errors" : "Cannot update partner ' . $id . ' to position ' . (int) $position . '"}';
                }

                break;
            }
        }
    }

    /**
     * Upload or remove the logo on the real object, replacing any previous
     * file so uploads do not accumulate as orphans.
     *
     * @param GfPartner $partner
     */
    private function applyLogo($partner)
    {
        if (Tools::isSubmit('gfRemoveImage')) {
            if ((string) $partner->logo !== '') {
                (new GFAssetUploader('partners'))->remove((string) $partner->logo);
            }

            $partner->logo = '';

            return;
        }

        if ($this->hasUploadedFile('file')) {
            $uploader = new GFAssetUploader('partners');
            $result = $uploader->upload('file');

            if ($result['success']) {
                if ((string) $partner->logo !== '') {
                    $uploader->remove((string) $partner->logo);
                }

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
            && (int) $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE
            && is_uploaded_file((string) $_FILES[$field]['tmp_name']);
    }

    /**
     * Build the raw HTML for a `'type' => 'file'` HelperForm input: the
     * current file's thumbnail plus a genuine "remove" checkbox (the
     * built-in `'type' => 'image'` / `'delete' => true` pair is not a
     * supported HelperForm input in this PrestaShop 1.6 codebase, so it
     * previously rendered nothing at all).
     *
     * @param  string $field
     * @param  string $removeField
     * @param  string $currentFile
     * @param  string $directory
     * @return string
     */
    private function assetFieldHtml($field, $removeField, $currentFile, $directory)
    {
        $html = '';

        if ($currentFile !== '') {
            $url = __PS_BASE_URI__ . 'uploads/establishments/' . $directory . '/' . $currentFile;
            $html .= '<div class="gf-asset-current" style="margin-bottom:8px;">'
                . '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="" style="max-height:80px;display:block;margin-bottom:4px;">'
                . '<label><input type="checkbox" name="' . htmlspecialchars($removeField, ENT_QUOTES, 'UTF-8') . '" value="1"> '
                . $this->l('Remove current file') . '</label>'
                . '</div>';
        }

        $html .= '<input type="file" name="' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '" accept="image/*">';

        return $html;
    }
}
