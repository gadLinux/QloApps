<?php
/**
 * 2026 GF Experiences
 *
 * Idempotent DDL primitives for migrations.
 *
 * Every method here is safe to call twice: adding a column that exists is a
 * no-op, dropping one that does not is a no-op. Migrations are written against
 * this helper rather than raw SQL so they can run against a database in any
 * state — a fresh install, a partially migrated one, or one already up to date.
 *
 * INFRASTRUCTURE LAYER — knows about MySQL, knows nothing about establishments.
 *
 * @author    GF Experiences <dev@gf-experiences.com>
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFSchemaHelper
{
    /** @var Db */
    protected $db;

    /** @var string[] Messages describing what actually changed. */
    protected $log = [];

    public function __construct(Db $db = null)
    {
        $this->db = $db ?: Db::getInstance();
    }

    /**
     * Prefixed table name. Migrations pass the bare name ('product').
     */
    public function table($name)
    {
        return _DB_PREFIX_ . $name;
    }

    public function tableExists($table)
    {
        $result = $this->db->getValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = \'' . pSQL(_DB_NAME_) . '\'
               AND TABLE_NAME = \'' . pSQL($this->table($table)) . '\''
        );

        return (int) $result > 0;
    }

    public function columnExists($table, $column)
    {
        $result = $this->db->getValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = \'' . pSQL(_DB_NAME_) . '\'
               AND TABLE_NAME = \'' . pSQL($this->table($table)) . '\'
               AND COLUMN_NAME = \'' . pSQL($column) . '\''
        );

        return (int) $result > 0;
    }

    public function indexExists($table, $indexName)
    {
        $result = $this->db->getValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = \'' . pSQL(_DB_NAME_) . '\'
               AND TABLE_NAME = \'' . pSQL($this->table($table)) . '\'
               AND INDEX_NAME = \'' . pSQL($indexName) . '\''
        );

        return (int) $result > 0;
    }

    /**
     * Add a column unless it is already there.
     *
     * @param string $table      Bare table name, without the DB prefix.
     * @param string $column     Column name.
     * @param string $definition SQL type and constraints, e.g. "VARCHAR(50) NULL".
     *
     * @return bool False only when a genuinely missing column could not be added.
     */
    public function addColumn($table, $column, $definition)
    {
        if ($this->columnExists($table, $column)) {
            return true;
        }

        $ok = $this->db->execute(
            'ALTER TABLE `' . bqSQL($this->table($table)) . '`
             ADD COLUMN `' . bqSQL($column) . '` ' . $definition
        );

        $this->log[] = ($ok ? '+ column ' : '! failed column ') . $table . '.' . $column;

        return (bool) $ok;
    }

    public function dropColumn($table, $column)
    {
        if (!$this->columnExists($table, $column)) {
            return true;
        }

        $ok = $this->db->execute(
            'ALTER TABLE `' . bqSQL($this->table($table)) . '`
             DROP COLUMN `' . bqSQL($column) . '`'
        );

        $this->log[] = ($ok ? '- column ' : '! failed drop ') . $table . '.' . $column;

        return (bool) $ok;
    }

    /**
     * @param string   $table
     * @param string   $indexName
     * @param string[] $columns
     */
    public function addIndex($table, $indexName, array $columns)
    {
        if ($this->indexExists($table, $indexName)) {
            return true;
        }

        $quoted = array_map(function ($column) {
            return '`' . bqSQL($column) . '`';
        }, $columns);

        $ok = $this->db->execute(
            'ALTER TABLE `' . bqSQL($this->table($table)) . '`
             ADD INDEX `' . bqSQL($indexName) . '` (' . implode(', ', $quoted) . ')'
        );

        $this->log[] = ($ok ? '+ index ' : '! failed index ') . $table . '.' . $indexName;

        return (bool) $ok;
    }

    public function dropIndex($table, $indexName)
    {
        if (!$this->indexExists($table, $indexName)) {
            return true;
        }

        $ok = $this->db->execute(
            'ALTER TABLE `' . bqSQL($this->table($table)) . '`
             DROP INDEX `' . bqSQL($indexName) . '`'
        );

        $this->log[] = ($ok ? '- index ' : '! failed drop index ') . $table . '.' . $indexName;

        return (bool) $ok;
    }

    /**
     * Create a table unless it exists. The body is the column list and any
     * keys, without the surrounding CREATE TABLE (...) and without the engine
     * clause, which is appended here so every GF table matches the shop.
     */
    public function createTable($table, $body)
    {
        if ($this->tableExists($table)) {
            return true;
        }

        $ok = $this->db->execute(
            'CREATE TABLE `' . bqSQL($this->table($table)) . '` (' . $body . ')
             ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8'
        );

        $this->log[] = ($ok ? '+ table ' : '! failed table ') . $table;

        return (bool) $ok;
    }

    public function dropTable($table)
    {
        if (!$this->tableExists($table)) {
            return true;
        }

        $ok = $this->db->execute('DROP TABLE `' . bqSQL($this->table($table)) . '`');

        $this->log[] = ($ok ? '- table ' : '! failed drop table ') . $table;

        return (bool) $ok;
    }

    /**
     * What this helper actually changed, for the migration report.
     *
     * @return string[]
     */
    public function getLog()
    {
        return $this->log;
    }

    public function clearLog()
    {
        $this->log = [];
    }
}
