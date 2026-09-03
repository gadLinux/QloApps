<?php
/**
 * 2026 GF Experiences
 *
 * Schema helper that records DDL instead of executing it.
 *
 * Migrations can then be unit tested for *what* they would change, with the
 * real DDL covered by the integration suite.
 */

class GFRecordingSchemaHelper extends GFSchemaHelper
{
    /** @var array<int, array{op: string, table: string, name: string}> */
    private $operations = [];

    /** @var string[] Fully-qualified "table.column" names to report as existing. */
    private $existing;

    /** @var string|null "table.name" whose next operation must fail. */
    private $failOn;

    /**
     * @param string[] $existing Objects that already exist in the schema.
     */
    public function __construct(array $existing = [], $failOn = null)
    {
        $this->existing = $existing;
        $this->failOn = $failOn;
    }

    public function columnExists($table, $column)
    {
        return in_array($table . '.' . $column, $this->existing, true);
    }

    public function indexExists($table, $indexName)
    {
        return in_array($table . '.' . $indexName, $this->existing, true);
    }

    public function tableExists($table)
    {
        return in_array($table, $this->existing, true);
    }

    public function addColumn($table, $column, $definition)
    {
        return $this->record('addColumn', $table, $column, $this->columnExists($table, $column));
    }

    public function dropColumn($table, $column)
    {
        return $this->record('dropColumn', $table, $column, !$this->columnExists($table, $column));
    }

    public function addIndex($table, $indexName, array $columns)
    {
        return $this->record('addIndex', $table, $indexName, $this->indexExists($table, $indexName));
    }

    public function dropIndex($table, $indexName)
    {
        return $this->record('dropIndex', $table, $indexName, !$this->indexExists($table, $indexName));
    }

    /**
     * @return array<int, array{op: string, table: string, name: string}>
     */
    public function getOperations()
    {
        return $this->operations;
    }

    /**
     * @return string[] "op:table.name" for concise assertions.
     */
    public function getOperationKeys()
    {
        return array_map(function (array $operation) {
            return $operation['op'] . ':' . $operation['table'] . '.' . $operation['name'];
        }, $this->operations);
    }

    private function record($op, $table, $name, $skip)
    {
        if ($skip) {
            return true;
        }

        if ($this->failOn === $table . '.' . $name) {
            return false;
        }

        $this->operations[] = ['op' => $op, 'table' => $table, 'name' => $name];
        $this->log[] = $op . ' ' . $table . '.' . $name;

        return true;
    }
}
