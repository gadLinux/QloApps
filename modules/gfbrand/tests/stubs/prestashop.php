<?php
/**
 * 2026 GF Experiences
 *
 * Minimal PrestaShop surface for unit tests.
 *
 * Unit tests exercise the layers that hold logic, and those layers touch only a
 * handful of PrestaShop symbols. Defining just those here keeps the unit suite
 * free of the framework: no database, no config, no 30-second bootstrap.
 * Integration tests load the real thing instead.
 */

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '1.6.1.23');
}

if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}

if (!defined('_DB_NAME_')) {
    define('_DB_NAME_', 'qloapps_test');
}

if (!defined('_PS_USE_SQL_SLAVE_')) {
    define('_PS_USE_SQL_SLAVE_', false);
}

if (!defined('_MYSQL_ENGINE_')) {
    define('_MYSQL_ENGINE_', 'InnoDB');
}

// Point the path constants at this checkout, so the classes that discover
// files on disk — the image locator, the placeholder generator — resolve their
// real defaults instead of needing every path injected.
if (!defined('_PS_MODULE_DIR_')) {
    define('_PS_MODULE_DIR_', dirname(dirname(dirname(__DIR__))) . '/');
}

if (!defined('_PS_ROOT_DIR_')) {
    define('_PS_ROOT_DIR_', dirname(_PS_MODULE_DIR_));
}

if (!function_exists('pSQL')) {
    /**
     * Escaping is asserted on in integration tests against the real driver.
     * Here it only has to be deterministic and quote-safe.
     */
    function pSQL($string, $htmlOk = false)
    {
        return addslashes((string) $string);
    }
}

if (!function_exists('bqSQL')) {
    function bqSQL($string)
    {
        return str_replace('`', '', (string) $string);
    }
}

if (!class_exists('Tools')) {
    class Tools
    {
        public static function strtoupper($string)
        {
            return mb_strtoupper((string) $string, 'UTF-8');
        }

        public static function strtolower($string)
        {
            return mb_strtolower((string) $string, 'UTF-8');
        }

        public static function substr($string, $start, $length = null)
        {
            return mb_substr((string) $string, $start, $length, 'UTF-8');
        }

        public static function strlen($string)
        {
            return mb_strlen((string) $string, 'UTF-8');
        }

        public static function link_rewrite($string)
        {
            $slug = mb_strtolower((string) $string, 'UTF-8');
            $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug);

            return trim((string) $slug, '-');
        }
    }
}
