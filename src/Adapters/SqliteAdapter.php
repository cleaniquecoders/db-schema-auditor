<?php

namespace CleaniqueCoders\DbSchemaAuditor\Adapters;

class SqliteAdapter extends AbstractDatabaseAdapter
{
    public function getTables(): array
    {
        $query = "
            SELECT name
            FROM sqlite_master
            WHERE type = 'table'
            AND name NOT LIKE 'sqlite_%'
            ORDER BY name
        ";

        return collect($this->getConnection()->select($query))
            ->pluck('name')
            ->toArray();
    }

    public function getColumns(string $tableName): array
    {
        $query = "PRAGMA table_info({$tableName})";
        $columns = $this->getConnection()->select($query);

        // Transform to match other adapters' structure
        return collect($columns)->map(function ($column) {
            return (object) [
                'name' => $column->name,
                'type' => $column->type,
                'nullable' => $column->notnull ? 'NO' : 'YES',
                'default_value' => $column->dflt_value,
                'max_length' => null,
                'is_primary' => $column->pk,
            ];
        })->toArray();
    }

    public function getIndexes(string $tableName): array
    {
        $query = "PRAGMA index_list({$tableName})";
        $indexes = $this->getConnection()->select($query);

        $result = [];
        foreach ($indexes as $index) {
            $indexInfoQuery = "PRAGMA index_info({$index->name})";
            $indexInfo = $this->getConnection()->select($indexInfoQuery);

            foreach ($indexInfo as $info) {
                $result[] = (object) [
                    'name' => $index->name,
                    'column_name' => $info->name,
                    'is_unique' => $index->unique,
                    'is_primary' => str_contains($index->name, 'primary') || str_contains($index->name, 'pk'),
                    'type' => 'BTREE',
                ];
            }
        }

        return $result;
    }

    public function getForeignKeys(string $tableName): array
    {
        $query = "PRAGMA foreign_key_list({$tableName})";
        $foreignKeys = $this->getConnection()->select($query);

        return collect($foreignKeys)->map(function ($fk) {
            return (object) [
                'name' => "fk_{$fk->from}_{$fk->table}",
                'column_name' => $fk->from,
                'referenced_table' => $fk->table,
                'referenced_column' => $fk->to,
            ];
        })->toArray();
    }

    public function getUniqueConstraints(string $tableName): array
    {
        // SQLite doesn't have a direct way to get unique constraints separate from indexes
        // We'll use the index information and filter for unique ones
        $indexes = $this->getIndexes($tableName);

        return collect($indexes)
            ->filter(fn ($index) => $index->is_unique && ! $index->is_primary)
            ->map(function ($index) {
                return (object) [
                    'name' => $index->name,
                    'column_name' => $index->column_name,
                ];
            })
            ->toArray();
    }

    public function getOrphanedRecordsCount(string $table, string $column, string $referencedTable, string $referencedColumn): int
    {
        $query = "
            SELECT COUNT(*) as count
            FROM \"{$table}\" t
            LEFT JOIN \"{$referencedTable}\" r ON t.\"{$column}\" = r.\"{$referencedColumn}\"
            WHERE t.\"{$column}\" IS NOT NULL
            AND r.\"{$referencedColumn}\" IS NULL
        ";

        $result = $this->getConnection()->selectOne($query);

        return $result->count ?? 0;
    }
}
