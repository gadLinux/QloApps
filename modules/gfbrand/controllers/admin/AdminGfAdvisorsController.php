<?php
/**
 * 2026 GF Experiences
 *
 * The back-office screen for travel advisors — story 1.11, AC-2.
 *
 * A standard ModuleAdminController: create, edit, deactivate and delete with
 * no developer, plus the language tabs ObjectModel gives a _lang table for
 * free (`$this->lang = true` and `'lang' => true` on each translated input —
 * both are required, or the _lang join is never added to the list query and
 * the required multilang name can never be posted).
 *
 * Position reordering is stock HelperList drag-and-drop, wired through
 * ajaxProcessUpdatePositions()/GfAdvisor::updatePosition(), the same shape
 * core uses for Carrier.
 *
 * The photograph is not a Product Image: it is moved into
 * uploads/establishments/advisors/ and stored by file name. copyFromPost()
 * is the hook point for that — it runs on the real $object right before
 * ObjectModel::add()/update(), for both the add and the edit path, which
 * postProcess() cannot guarantee (the add path has no object yet there).
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
        $this->lang = true;
        $this->position_identifier = 'id_gf_advisor';

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
        /** @var GfAdvisor $advisor */
        $advisor = $this->object;
        $currentImage = ($advisor && $advisor->id && (string) $advisor->image !== '') ? (string) $advisor->image : '';

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
                    'lang' => true,
                    'required' => true,
                    'size' => 40,
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Regions served'),
                    'name' => 'regions_served',
                    'lang' => true,
                    'size' => 60,
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->l('Bio'),
                    'name' => 'bio',
                    'lang' => true,
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
                    'desc' => $this->l('Used for the tel: link on the card. Include the leading +.'),
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
                    'label' => $this->l('Email'),
                    'name' => 'email',
                    'size' => 40,
                    'validate' => 'isEmail',
                ],
                [
                    'type' => 'file',
                    'label' => $this->l('Photograph'),
                    'name' => 'file',
                    'file' => $this->assetFieldHtml('file', 'gfRemoveImage', $currentImage, 'advisors'),
                    'desc' => $this->l('JPG, PNG, GIF or WebP, up to 5 MB.'),
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
     * Give a newly added advisor the next position, so it sorts after every
     * existing row instead of defaulting to 0 and sorting first.
     *
     * @param  GfAdvisor $object
     * @return bool
     */
    protected function beforeAdd($object)
    {
        if (empty($object->position)) {
            $object->position = (int) Db::getInstance()->getValue(
                'SELECT MAX(`position`) FROM `' . _DB_PREFIX_ . 'gf_advisor`'
            ) + 1;
        }

        return true;
    }

    /**
     * Runs on the real object right before add()/update() for both the add
     * and the edit path — the correct hook point for the upload, unlike
     * postProcess() (no object exists yet there when adding).
     *
     * @param GfAdvisor $object
     * @param string    $table
     */
    protected function copyFromPost(&$object, $table)
    {
        parent::copyFromPost($object, $table);

        if ($table === $this->table) {
            $this->applyImage($object);
        }
    }

    /**
     * Delete the advisor's photograph along with the row, so removing an
     * advisor does not leave an orphaned file behind.
     */
    public function processDelete()
    {
        $object = $this->loadObject();

        if (Validate::isLoadedObject($object) && (string) $object->image !== '') {
            (new GFAssetUploader('advisors'))->remove((string) $object->image);
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
                $advisor = new GfAdvisor($id);

                if (Validate::isLoadedObject($advisor) && $advisor->updatePosition($way, $position)) {
                    echo 'ok position ' . (int) $position . ' for advisor ' . $id;
                } else {
                    echo '{"hasError" : true, "errors" : "Cannot update advisor ' . $id . ' to position ' . (int) $position . '"}';
                }

                break;
            }
        }
    }

    /**
     * Upload or remove the photograph on the real object, replacing any
     * previous file so uploads do not accumulate as orphans.
     *
     * @param GfAdvisor $advisor
     */
    private function applyImage($advisor)
    {
        if (Tools::isSubmit('gfRemoveImage')) {
            if ((string) $advisor->image !== '') {
                (new GFAssetUploader('advisors'))->remove((string) $advisor->image);
            }

            $advisor->image = '';

            return;
        }

        if ($this->hasUploadedFile('file')) {
            $uploader = new GFAssetUploader('advisors');
            $result = $uploader->upload('file');

            if ($result['success']) {
                if ((string) $advisor->image !== '') {
                    $uploader->remove((string) $advisor->image);
                }

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
