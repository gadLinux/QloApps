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
    '/lib/Repository/GFHotelRepository.php',
    '/lib/Repository/GFRoomType.php',
    '/lib/Repository/GFImagePlaceholder.php',
    '/lib/Service/GFImportException.php',
    '/lib/Service/GFErrorCollectingController.php',
    '/lib/Service/GFImportResult.php',
    '/lib/Service/GFEstablishmentCsvReader.php',
    '/lib/Service/GFImageLocator.php',
    '/lib/Service/GFReadableImage.php',
    '/lib/Service/GFPlaceholderImageGenerator.php',
    '/lib/Service/GFProductImageFactory.php',
    '/lib/Service/GFHotelImageFactory.php',
    '/lib/Service/GFEstablishmentProductFactory.php',
    '/lib/Service/GFCategoryTreeBuilder.php',
    '/lib/Service/GFHotelFactory.php',
    '/lib/Service/GFRoomTypeFactory.php',
    '/lib/Service/GFHotelProvisioner.php',
    '/lib/Service/GFEstablishmentImporter.php',
    '/lib/Repository/GFAdvisorSeed.php',
    '/lib/Repository/GFPartnerSeed.php',
    '/lib/Service/GFAdvisorPartnerCsvReader.php',
    '/lib/Service/GFAdvisorPartnerImporter.php',
    '/lib/Service/GFAdvisorPartnerListing.php',
    '/migrations/GFMigration20260903001Establishments.php',
    '/migrations/GFMigration20260907001BookingInquiry.php',
    '/migrations/GFMigration20260909001AdvisorsPartners.php',
    '/classes/GfAdvisor.php',
    '/classes/GfPartner.php',
];

foreach ($classes as $relativePath) {
    require_once $moduleDir . $relativePath;
}
