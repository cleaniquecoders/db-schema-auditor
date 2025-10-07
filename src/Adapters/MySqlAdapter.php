<?php

namespace CleaniqueCoders\DbSchemaAuditor\Adapters;

class MySqlAdapter extends AbstractDatabaseAdapter
{
    public function getTables(): array
    {
        $query = "
            SELECT TABLE_NAME as name
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = ?
            AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        ";

        return collect($this->getConnection()->select($query, [$this->getDatabaseName()]))
            ->pluck('name')
            ->toArray();
    }

    public function getColumns(string $tableName): array
    {
        $query = '
            SELECT
                COLUMN_NAME as name,
                DATA_TYPE as type,
                IS_NULLABLE as nullable,
                COLUMN_DEFAULT as default_value,
                CHARACTER_MAXIMUM_LENGTH as max_length,
                COLUMN_KEY as key_type,
                EXTRA as extra
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ';

        return $this->getConnection()->select($query, [$this->getDatabaseName(), $tableName]);
    }

    public function getIndexes(string $tableName): array
    {
        $query = "
            SELECT
                INDEX_NAME as name,
                COLUMN_NAME as column_name,
                NON_UNIQUE as non_unique,
                INDEX_TYPE as type,
                CASE WHEN INDEX_NAME = 'PRIMARY' THEN 1 ELSE 0 END as is_primary
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            ORDER BY INDEX_NAME, SEQ_IN_INDEX
        ";

        return $this->getConnection()->select($query, [$this->getDatabaseName(), $tableName]);
    }

    public function getForeignKeys(string $tableName): array
    {
        $query = '
            SELECT
                CONSTRAINT_NAME as name,
                COLUMN_NAME as column_name,
                REFERENCED_TABLE_NAME as referenced_table,
                REFERENCED_COLUMN_NAME as referenced_column
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION
        ';

        return $this->getConnection()->select($query, [$this->getDatabaseName(), $tableName]);
    }

    public function getUniqueConstraints(string $tableName): array
    {
        $query = "
            SELECT
                INDEX_NAME as name,
                COLUMN_NAME as column_name
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND NON_UNIQUE = 0
            AND INDEX_NAME != 'PRIMARY'
            ORDER BY INDEX_NAME, SEQ_IN_INDEX
        ";

        return $this->getConnection()->select($query, [$this->getDatabaseName(), $tableName]);
    }

    public function getOrphanedRecordsCount(string $table, string $column, string $referencedTable, string $referencedColumn): int
    {
        $query = "
            SELECT COUNT(*) as count
            FROM `{$table}` t
            LEFT JOIN `{$referencedTable}` r ON t.`{$column}` = r.`{$referencedColumn}`
            WHERE t.`{$column}` IS NOT NULL
            AND r.`{$referencedColumn}` IS NULL
        ";

        $result = $this->getConnection()->selectOne($query);

        return $result->count ?? 0;
    }
}
