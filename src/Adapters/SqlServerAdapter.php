<?php

namespace CleaniqueCoders\DbSchemaAuditor\Adapters;

class SqlServerAdapter extends AbstractDatabaseAdapter
{
    public function getTables(): array
    {
        $query = "
            SELECT TABLE_NAME as name
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_TYPE = 'BASE TABLE'
            AND TABLE_CATALOG = ?
            ORDER BY TABLE_NAME
        ";

        return collect($this->getConnection()->select($query, [$this->getDatabaseName()]))
            ->pluck('name')
            ->toArray();
    }

    public function getColumns(string $tableName): array
    {
        $query = "
            SELECT
                COLUMN_NAME as name,
                DATA_TYPE as type,
                IS_NULLABLE as nullable,
                COLUMN_DEFAULT as default_value,
                CHARACTER_MAXIMUM_LENGTH as max_length
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = ?
            AND TABLE_SCHEMA = 'dbo'
            ORDER BY ORDINAL_POSITION
        ";

        return $this->getConnection()->select($query, [$tableName]);
    }

    public function getIndexes(string $tableName): array
    {
        $query = "
            SELECT
                i.name AS name,
                i.type_desc AS type,
                i.is_unique,
                i.is_primary_key as is_primary,
                COL_NAME(ic.object_id, ic.column_id) AS column_name
            FROM sys.indexes i
            INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
            WHERE i.object_id = OBJECT_ID('dbo.' + ?)
            ORDER BY i.name, ic.key_ordinal
        ";

        return $this->getConnection()->select($query, [$tableName]);
    }

    public function getForeignKeys(string $tableName): array
    {
        $query = "
            SELECT
                fk.name AS name,
                COL_NAME(fkc.parent_object_id, fkc.parent_column_id) AS column_name,
                OBJECT_NAME(fk.referenced_object_id) AS referenced_table,
                COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) AS referenced_column
            FROM sys.foreign_keys AS fk
            INNER JOIN sys.foreign_key_columns AS fkc ON fk.object_id = fkc.constraint_object_id
            WHERE fk.parent_object_id = OBJECT_ID('dbo.' + ?)
            ORDER BY fk.name
        ";

        return $this->getConnection()->select($query, [$tableName]);
    }

    public function getUniqueConstraints(string $tableName): array
    {
        $query = "
            SELECT
                i.name as name,
                COL_NAME(ic.object_id, ic.column_id) as column_name
            FROM sys.indexes i
            INNER JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
            WHERE i.object_id = OBJECT_ID('dbo.' + ?)
            AND i.is_unique = 1
            AND i.is_primary_key = 0
            ORDER BY i.name, ic.key_ordinal
        ";

        return $this->getConnection()->select($query, [$tableName]);
    }

    public function getOrphanedRecordsCount(string $table, string $column, string $referencedTable, string $referencedColumn): int
    {
        $query = "
            SELECT COUNT(*) as count
            FROM [{$table}] t
            LEFT JOIN [{$referencedTable}] r ON t.[{$column}] = r.[{$referencedColumn}]
            WHERE t.[{$column}] IS NOT NULL
            AND r.[{$referencedColumn}] IS NULL
        ";

        $result = $this->getConnection()->selectOne($query);

        return $result->count ?? 0;
    }
}
