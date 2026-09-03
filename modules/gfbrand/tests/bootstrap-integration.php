<?php
/**
 * 2026 GF Experiences
 *
 * Bootstrap for the integration suite.
 *
 * Loads the real PrestaShop kernel, so these tests exercise the actual
 * database, the actual Product model and the actual DDL. They must run inside
 * the container, where config/settings.inc.php points at a live schema.
 */

if (!defined('_PS_ROOT_DIR_')) {
    define('_PS_ROOT_DIR_', '/var/www/html');
}

require_once _PS_ROOT_DIR_ . '/config/config.inc.php';

$moduleDir = dirname(__DIR__);

require_once $moduleDir . '/lib/GFModuleServices.php';

$classes = [
    '/lib/Migration/GFMigrationInterface.php',
    '/lib/Migration/GFSchemaHelper.php',
    '/lib/Migration/GFMigrationResult.php',
    '/lib/Migration/GFMigrationRepository.php',
    '/lib/Migration/GFMigrationRunner.php',
    '/lib/Repository/GFEstablishment.php',
    '/lib/Repository/GFEstablishmentRepository.php',
    '/lib/Repository/GFImagePlaceholder.php',
    '/lib/Service/GFImportException.php',
    '/lib/Service/GFErrorCollectingController.php',
    '/lib/Service/GFImportResult.php',
    '/lib/Service/GFEstablishmentCsvReader.php',
    '/lib/Service/GFEstablishmentProductFactory.php',
    '/lib/Service/GFEstablishmentImporter.php',
    '/migrations/GFMigration20260903001Establishments.php',
];

foreach ($classes as $relativePath) {
    require_once $moduleDir . $relativePath;
}
