<?php

namespace CleaniqueCoders\DbSchemaAuditor\Actions;

use CleaniqueCoders\DbSchemaAuditor\Adapters\AbstractDatabaseAdapter;
use Lorisleiva\Actions\Concerns\AsAction;

class AuditDatabaseIntegrityAction
{
    use AsAction;

    public function handle(
        ?string $connectionName = null,
        array $config = []
    ): array {
        $adapter = AbstractDatabaseAdapter::create($connectionName);
        $tables = $adapter->getTables();

        $issues = [
            'missing_indexes' => [],
            'missing_unique_constraints' => [],
            'missing_foreign_keys' => [],
            'orphaned_records' => [],
            'suspicious_columns' => [],
        ];

        $stats = [
            'total_tables' => count($tables),
            'tables_scanned' => 0,
            'issues_found' => 0,
            'database_type' => $adapter->getDriverName(),
            'connection' => $connectionName ?? config('database.default'),
        ];

        foreach ($tables as $tableName) {
            $stats['tables_scanned']++;

            $tableIssues = $this->auditTable($adapter, $tableName, $config);

            foreach ($tableIssues as $type => $tableTypeIssues) {
                $issues[$type] = array_merge($issues[$type], $tableTypeIssues);
                $stats['issues_found'] += count($tableTypeIssues);
            }
        }

        return [
            'issues' => $issues,
            'stats' => $stats,
            'audit_time' => now()->toISOString(),
        ];
    }

    protected function auditTable(AbstractDatabaseAdapter $adapter, string $tableName, array $config): array
    {
        $issues = [
            'missing_indexes' => [],
            'missing_unique_constraints' => [],
            'missing_foreign_keys' => [],
            'orphaned_records' => [],
            'suspicious_columns' => [],
        ];

        $columns = $adapter->getColumns($tableName);
        $indexes = $adapter->getIndexes($tableName);
        $foreignKeys = $adapter->getForeignKeys($tableName);
        $uniqueConstraints = $adapter->getUniqueConstraints($tableName);

        // Extract column names from indexes for quick lookup
        $indexedColumns = collect($indexes)->pluck('column_name')->unique()->toArray();
        $uniqueColumns = collect($uniqueConstraints)->pluck('column_name')->unique()->toArray();
        $foreignKeyColumns = collect($foreignKeys)->pluck('column_name')->unique()->toArray();

        foreach ($columns as $column) {
            $columnName = $column->name;

            // Check for missing indexes on foreign key columns
            if (str_ends_with($columnName, '_id') &&
                $columnName !== 'id' &&
                ! in_array($columnName, $indexedColumns)) {

                $issues['missing_indexes'][] = [
                    'table' => $tableName,
                    'column' => $columnName,
                    'type' => 'foreign_key',
                    'severity' => 'high',
                    'recommendation' => "Add index on {$tableName}.{$columnName} for foreign key performance",
                ];
            }

            // Check for common indexable columns
            $commonIndexableColumns = $config['common_indexable_columns'] ?? [
                'email', 'uuid', 'status', 'type', 'code', 'slug', 'name',
            ];

            if (in_array($columnName, $commonIndexableColumns) &&
                ! in_array($columnName, $indexedColumns)) {

                $issues['missing_indexes'][] = [
                    'table' => $tableName,
                    'column' => $columnName,
                    'type' => 'common_field',
                    'severity' => 'medium',
                    'recommendation' => "Consider adding index on {$tableName}.{$columnName} for query performance",
                ];
            }

            // Check for unique constraint requirements
            $uniqueRequiredColumns = $config['unique_required_columns'] ?? [
                'email', 'uuid', 'slug', 'code', 'username',
            ];

            if (in_array($columnName, $uniqueRequiredColumns) &&
                ! in_array($columnName, $uniqueColumns)) {

                $issues['missing_unique_constraints'][] = [
                    'table' => $tableName,
                    'column' => $columnName,
                    'severity' => 'high',
                    'recommendation' => "Add unique constraint on {$tableName}.{$columnName} to prevent duplicates",
                ];
            }

            // Check for missing foreign key constraints
            if (str_ends_with($columnName, '_id') &&
                $columnName !== 'id' &&
                ! in_array($columnName, ['created_by', 'updated_by', 'deleted_by']) &&
                ! in_array($columnName, $foreignKeyColumns)) {

                $guessedTable = $this->guessReferencedTable($columnName);
                $issues['missing_foreign_keys'][] = [
                    'table' => $tableName,
                    'column' => $columnName,
                    'guessed_reference' => $guessedTable,
                    'severity' => 'medium',
                    'recommendation' => "Consider adding foreign key constraint for {$tableName}.{$columnName}",
                ];
            }

            // Check for suspicious column patterns
            $this->checkSuspiciousColumns($column, $tableName, $issues);
        }

        // Check for orphaned records
        foreach ($foreignKeys as $fk) {
            try {
                $orphanedCount = $adapter->getOrphanedRecordsCount(
                    $tableName,
                    $fk->column_name,
                    $fk->referenced_table,
                    $fk->referenced_column
                );

                if ($orphanedCount > 0) {
                    $issues['orphaned_records'][] = [
                        'table' => $tableName,
                        'column' => $fk->column_name,
                        'referenced_table' => $fk->referenced_table,
                        'referenced_column' => $fk->referenced_column,
                        'count' => $orphanedCount,
                        'severity' => $orphanedCount > 100 ? 'high' : 'medium',
                        'recommendation' => "Found {$orphanedCount} orphaned records in {$tableName}.{$fk->column_name}",
                    ];
                }
            } catch (\Exception $e) {
                // Log warning but continue audit
            }
        }

        return $issues;
    }

    protected function guessReferencedTable(string $columnName): string
    {
        if (! str_ends_with($columnName, '_id')) {
            return '';
        }

        $baseName = str_replace('_id', '', $columnName);

        // Handle common pluralization cases
        if (str_ends_with($baseName, 'y')) {
            return str_replace('y', 'ies', $baseName);
        } elseif (str_ends_with($baseName, 's')) {
            return $baseName.'es';
        } else {
            return $baseName.'s';
        }
    }

    protected function checkSuspiciousColumns($column, string $tableName, array &$issues): void
    {
        $columnName = $column->name;
        $columnType = $column->type ?? '';

        // Check for potential text fields that should be indexed
        if (str_contains(strtolower($columnType), 'text') &&
            in_array($columnName, ['description', 'content', 'notes', 'comments'])) {

            $issues['suspicious_columns'][] = [
                'table' => $tableName,
                'column' => $columnName,
                'issue' => 'large_text_field',
                'severity' => 'low',
                'recommendation' => "Consider full-text indexing for {$tableName}.{$columnName} if searchable",
            ];
        }

        // Check for nullable foreign keys
        if (str_ends_with($columnName, '_id') &&
            $columnName !== 'id' &&
            ($column->nullable ?? 'YES') === 'YES') {

            $issues['suspicious_columns'][] = [
                'table' => $tableName,
                'column' => $columnName,
                'issue' => 'nullable_foreign_key',
                'severity' => 'low',
                'recommendation' => "Review if {$tableName}.{$columnName} should be nullable",
            ];
        }

        // Check for potentially missing timestamps
        if ($tableName !== 'migrations' &&
            ! $this->hasTimestamps($tableName) &&
            ! str_contains($tableName, 'pivot')) {

            $issues['suspicious_columns'][] = [
                'table' => $tableName,
                'column' => 'timestamps',
                'issue' => 'missing_timestamps',
                'severity' => 'low',
                'recommendation' => "Consider adding created_at/updated_at timestamps to {$tableName}",
            ];
        }
    }

    protected function hasTimestamps(string $tableName): bool
    {
        // This would need to be checked per table, but for now we'll assume
        // most tables should have timestamps unless explicitly configured otherwise
        return true; // Simplified for now
    }
}
