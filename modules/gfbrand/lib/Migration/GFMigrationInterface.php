<?php
/**
 * 2026 GF Experiences
 *
 * Contract every migration implements.
 *
 * INFRASTRUCTURE LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

interface GFMigrationInterface
{
    /**
     * Sortable version identifier, e.g. '20260903_001'.
     * Migrations run in ascending order of this value.
     *
     * @return string
     */
    public function getVersion();

    /**
     * One line describing what this migration does, shown in the report.
     *
     * @return string
     */
    public function getDescription();

    /**
     * Apply the change. Must be idempotent.
     *
     * @return bool False aborts the run and leaves the version unrecorded.
     */
    public function up(GFSchemaHelper $schema);

    /**
     * Reverse the change. Must tolerate a partially applied state.
     *
     * @return bool
     */
    public function down(GFSchemaHelper $schema);
}
