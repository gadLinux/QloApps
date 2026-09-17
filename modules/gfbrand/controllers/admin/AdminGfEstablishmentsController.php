<?php
/**
 * 2026 GF Experiences
 *
 * The back-office screen for the homepage featured strip — story 1.17, AC-3.
 *
 * One checkbox per establishment: check the ones that belong on the homepage,
 * save, and the strip updates on reload with no deploy. Establishments live on
 * ps_product (a plain value object, not an ObjectModel — see GFEstablishment),
 * so this is a list-only screen with a single batch save rather than the usual
 * HelperForm add/edit flow: there is no "new establishment" here, only a flag
 * to toggle on rows the importer (or a hand-made product) already created.
 *
 * The rows come from GFEstablishmentRepository::findAllForFeaturedPicker() —
 * the same listable universe as the storefront, plus deactivated rows, so a
 * flagged establishment cannot silently fall off the picker the moment it is
 * deactivated.
 *
 * PRESENTATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminGfEstablishmentsController extends ModuleAdminController
{
    /**
     * @var GFEstablishmentRepository
     */
    private $repository;

    public function __construct()
    {
        $this->bootstrap = true;
        $this->table = 'product';
        $this->identifier = 'id_product';

        parent::__construct();
    }

    /**
     * List-only screen: no row actions, no add button, no pagination beyond
     * what the catalogue actually holds (dozens, not thousands).
     */
    public function renderView()
    {
        $rows = $this->repository()->findAllForFeaturedPicker((int) Context::getContext()->language->id);

        $this->context->smarty->assign([
            'gf_featured_rows' => $rows,
            'gf_action_url' => $this->context->link->getAdminLink('AdminGfEstablishments'),
        ]);

        return $this->renderTemplate('views/templates/admin/featured_picker.tpl');
    }

    /**
     * Save the checkbox state in one batch — see
     * GFEstablishmentRepository::setFeaturedHome() for why this is a set
     * operation rather than one write per checkbox.
     */
    public function postProcess()
    {
        if (!Tools::isSubmit('submitGfFeatured')) {
            return parent::postProcess();
        }

        $checked = array_map('intval', (array) Tools::getValue('gf_featured'));

        if (!$this->repository()->isSchemaReady()) {
            $this->errors[] = $this->l('The establishments schema is not ready yet.');

            return false;
        }

        if ($this->repository()->setFeaturedHome($checked)) {
            $this->confirmations[] = $this->l('Featured establishments saved.');
        } else {
            $this->errors[] = $this->l('Could not save featured establishments.');
        }

        return true;
    }

    /**
     * @return GFEstablishmentRepository
     */
    private function repository()
    {
        if (!$this->repository) {
            require_once dirname(__FILE__) . '/../../lib/Repository/GFEstablishmentRepository.php';
            $this->repository = new GFEstablishmentRepository();
        }

        return $this->repository;
    }

    /**
     * @param  string $templatePath
     * @return string
     */
    private function renderTemplate($templatePath)
    {
        $tpl = $this->context->smarty->createTemplate(
            $this->module->getLocalPath() . $templatePath,
            $this->context->smarty
        );

        return $tpl->fetch();
    }
}
