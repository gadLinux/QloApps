<?php
/**
 * 2026 GF Experiences
 *
 * Bootstrap for the unit suite.
 *
 * Loads the PrestaShop stubs, then the module classes that carry logic. No
 * database connection and no PrestaShop kernel, so the suite runs anywhere in
 * milliseconds.
 */

require_once __DIR__ . '/stubs/prestashop.php';

$moduleDir = dirname(__DIR__);

$classes = [
    // Infrastructure
    '/lib/Migration/GFMigrationInterface.php',
    '/lib/Migration/GFSchemaHelper.php',
    '/lib/Migration/GFMigrationResult.php',
    '/lib/Migration/GFMigrationRepository.php',
    '/lib/Migration/GFMigrationRunner.php',
    // Domain
    '/lib/Repository/GFEstablishment.php',
    // Application
    '/lib/Service/GFImportException.php',
    '/lib/Service/GFImportResult.php',
    '/lib/Service/GFEstablishmentCsvReader.php',
    // Migrations
    '/migrations/GFMigration20260903001Establishments.php',
];

foreach ($classes as $relativePath) {
    require_once $moduleDir . $relativePath;
}

// Test doubles, loaded after the classes they extend.
require_once __DIR__ . '/stubs/GFInMemoryMigrationRepository.php';
require_once __DIR__ . '/stubs/GFRecordingSchemaHelper.php';
