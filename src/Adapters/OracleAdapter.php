<?php

namespace CleaniqueCoders\DbSchemaAuditor\Adapters;

class OracleAdapter extends AbstractDatabaseAdapter
{
    public function getTables(): array
    {
        $query = '
            SELECT table_name as name
            FROM user_tables
            ORDER BY table_name
        ';

        return collect($this->getConnection()->select($query))
            ->pluck('name')
            ->toArray();
    }

    public function getColumns(string $tableName): array
    {
        $query = '
            SELECT
                column_name as name,
                data_type as type,
                nullable,
                data_default as default_value,
                data_length as max_length
            FROM user_tab_columns
            WHERE table_name = UPPER(?)
            ORDER BY column_id
        ';

        return $this->getConnection()->select($query, [$tableName]);
    }

    public function getIndexes(string $tableName): array
    {
        $query = "
            SELECT
                i.index_name as name,
                ic.column_name as column_name,
                CASE WHEN i.uniqueness = 'UNIQUE' THEN 1 ELSE 0 END as is_unique,
                CASE WHEN c.constraint_type = 'P' THEN 1 ELSE 0 END as is_primary,
                i.index_type as type
            FROM user_indexes i
            JOIN user_ind_columns ic ON i.index_name = ic.index_name
            LEFT JOIN user_constraints c ON i.index_name = c.index_name
            WHERE i.table_name = UPPER(?)
            ORDER BY i.index_name, ic.column_position
        ";

        return $this->getConnection()->select($query, [$tableName]);
    }

    public function getForeignKeys(string $tableName): array
    {
        $query = "
            SELECT
                c.constraint_name as name,
                cc.column_name as column_name,
                r_cc.table_name as referenced_table,
                r_cc.column_name as referenced_column
            FROM user_constraints c
            JOIN user_cons_columns cc ON c.constraint_name = cc.constraint_name
            JOIN user_cons_columns r_cc ON c.r_constraint_name = r_cc.constraint_name
            WHERE c.constraint_type = 'R'
            AND c.table_name = UPPER(?)
            ORDER BY c.constraint_name
        ";

        return $this->getConnection()->select($query, [$tableName]);
    }

    public function getUniqueConstraints(string $tableName): array
    {
        $query = "
            SELECT
                c.constraint_name as name,
                cc.column_name as column_name
            FROM user_constraints c
            JOIN user_cons_columns cc ON c.constraint_name = cc.constraint_name
            WHERE c.constraint_type = 'U'
            AND c.table_name = UPPER(?)
            ORDER BY c.constraint_name, cc.position
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
