<?php
/**
 * 2026 GF Experiences
 *
 * Back-office panel for the establishment import — Story 1.8.
 *
 * Turns import requests into calls on the importer and renders the outcome.
 * Holds no SQL and no import logic of its own.
 *
 * PRESENTATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFImportPanel
{
    const ACTION_IMPORT = 'submitGfImport';
    const ACTION_RELOAD = 'submitGfReload';

    /** @var GFEstablishmentImporter */
    private $importer;

    /** @var GFEstablishmentRepository */
    private $repository;

    /** @var GFMigrationRunner */
    private $migrationRunner;

    /** @var Module Supplies translation and the standard admin notices. */
    private $module;

    /** @var string */
    private $csvPath;

    public function __construct(
        GFEstablishmentImporter $importer,
        GFEstablishmentRepository $repository,
        GFMigrationRunner $migrationRunner,
        Module $module,
        $csvPath
    ) {
        $this->importer = $importer;
        $this->repository = $repository;
        $this->migrationRunner = $migrationRunner;
        $this->module = $module;
        $this->csvPath = $csvPath;
    }

    /**
     * Run whichever import the operator asked for.
     *
     * @return string HTML notice, empty when no action was requested.
     */
    public function handleRequest()
    {
        if (Tools::isSubmit(self::ACTION_RELOAD)) {
            return $this->renderOutcome($this->importer->reload($this->csvPath), true);
        }

        if (Tools::isSubmit(self::ACTION_IMPORT)) {
            return $this->renderOutcome($this->importer->import($this->csvPath), false);
        }

        return '';
    }

    /**
     * @return string
     */
    public function render()
    {
        if (!$this->repository->isSchemaReady()) {
            return $this->module->displayWarning(
                $this->l('The establishment columns are missing. Reinstall the module to run its migrations.')
            );
        }

        return '<form method="post" action="">'
            . '<div class="panel">'
            . '<div class="panel-heading"><i class="icon-database"></i> ' . $this->l('GF Establishments') . '</div>'
            . '<div class="form-wrapper">' . $this->renderStatus() . $this->renderHelp() . '</div>'
            . '<div class="panel-footer">' . $this->renderButtons() . '</div>'
            . '</div></form>';
    }

    private function renderStatus()
    {
        $total = $this->repository->countAll();

        if ($total === 0) {
            return '<p><strong>' . $this->l('No establishments loaded yet.') . '</strong></p>';
        }

        $parts = [];
        foreach ($this->repository->countByType() as $type => $count) {
            $parts[] = (int) $count . ' ' . Tools::safeOutput($type);
        }

        return '<p><strong>'
            . sprintf($this->l('%1$d establishments loaded (%2$s)'), $total, implode(', ', $parts))
            . '</strong></p>';
    }

    private function renderHelp()
    {
        $lastImport = Configuration::get('GFBRAND_LAST_IMPORT');
        $pending = $this->migrationRunner->hasPending();

        $lines = [
            $this->l('Source:') . ' <code>' . Tools::safeOutput(basename($this->csvPath)) . '</code>',
            $this->l('Schema:') . ' ' . ($pending ? $this->l('migrations pending') : $this->l('up to date')),
        ];

        if ($lastImport) {
            $lines[] = $this->l('Last import:') . ' ' . Tools::safeOutput($lastImport);
        }

        return '<p class="help-block">' . implode('<br/>', $lines) . '</p>'
            . '<p class="help-block">'
            . $this->l('Import matches rows by their source id, so it can be run repeatedly without creating duplicates. Reload deletes every imported establishment first and then imports again — products created by hand in the back office are never touched.')
            . '</p>';
    }

    private function renderButtons()
    {
        $confirm = $this->l('Delete all imported establishments and import again?');

        return '<button type="submit" name="' . self::ACTION_IMPORT . '" class="btn btn-default pull-right">'
            . '<i class="process-icon-refresh"></i> ' . $this->l('Import / update')
            . '</button>'
            . '<button type="submit" name="' . self::ACTION_RELOAD . '" class="btn btn-default pull-right"'
            . ' onclick="return confirm(\'' . addslashes($confirm) . '\');">'
            . '<i class="process-icon-eraser"></i> ' . $this->l('Full reload')
            . '</button>';
    }

    private function renderOutcome(GFImportResult $result, $wasReload)
    {
        $summary = sprintf(
            $this->l('%1$d created, %2$d updated, %3$d failed'),
            $result->getCreated(),
            $result->getUpdated(),
            $result->getFailed()
        );

        if ($wasReload) {
            $summary = sprintf($this->l('%d removed, '), $result->getDeleted()) . $summary;
        }

        if ($result->isSuccessful()) {
            return $this->module->displayConfirmation($summary);
        }

        return $this->module->displayWarning(
            $summary . ' — ' . Tools::safeOutput(implode('; ', $result->getErrors()))
        );
    }

    private function l($string)
    {
        return $this->module->l($string, 'gfimportpanel');
    }
}
