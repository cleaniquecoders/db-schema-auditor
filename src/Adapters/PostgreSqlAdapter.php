<?php

namespace CleaniqueCoders\DbSchemaAuditor\Adapters;

class PostgreSqlAdapter extends AbstractDatabaseAdapter
{
    public function getTables(): array
    {
        $query = "
            SELECT tablename as name
            FROM pg_tables
            WHERE schemaname = 'public'
            ORDER BY tablename
        ";

        return collect($this->getConnection()->select($query))
            ->pluck('name')
            ->toArray();
    }

    public function getColumns(string $tableName): array
    {
        $query = "
            SELECT
                column_name as name,
                data_type as type,
                is_nullable as nullable,
                column_default as default_value,
                character_maximum_length as max_length
            FROM information_schema.columns
            WHERE table_schema = 'public'
            AND table_name = ?
            ORDER BY ordinal_position
        ";

        return $this->getConnection()->select($query, [$tableName]);
    }

    public function getIndexes(string $tableName): array
    {
        $query = "
            SELECT
                i.relname as name,
                a.attname as column_name,
                ix.indisunique as is_unique,
                ix.indisprimary as is_primary,
                am.amname as type
            FROM pg_class t
            JOIN pg_index ix ON t.oid = ix.indrelid
            JOIN pg_class i ON i.oid = ix.indexrelid
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
            JOIN pg_am am ON i.relam = am.oid
            WHERE t.relname = ?
            AND t.relkind = 'r'
            ORDER BY i.relname
        ";

        return $this->getConnection()->select($query, [$tableName]);
    }

    public function getForeignKeys(string $tableName): array
    {
        $query = "
            SELECT
                tc.constraint_name as name,
                kcu.column_name as column_name,
                ccu.table_name as referenced_table,
                ccu.column_name as referenced_column
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
                AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage ccu
                ON ccu.constraint_name = tc.constraint_name
                AND ccu.table_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
            AND tc.table_name = ?
            ORDER BY tc.constraint_name
        ";

        return $this->getConnection()->select($query, [$tableName]);
    }

    public function getUniqueConstraints(string $tableName): array
    {
        $query = "
            SELECT
                tc.constraint_name as name,
                kcu.column_name as column_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
                AND tc.table_schema = kcu.table_schema
            WHERE tc.constraint_type = 'UNIQUE'
            AND tc.table_name = ?
            ORDER BY tc.constraint_name
        ";

        return $this->getConnection()->select($query, [$tableName]);
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
