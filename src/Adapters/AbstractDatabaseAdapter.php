<?php

namespace CleaniqueCoders\DbSchemaAuditor\Adapters;

use Illuminate\Support\Facades\DB;

abstract class AbstractDatabaseAdapter
{
    protected string $connectionName;

    public function __construct(?string $connectionName = null)
    {
        $this->connectionName = $connectionName ?? config('database.default');
    }

    /**
     * Get all tables in the database
     */
    abstract public function getTables(): array;

    /**
     * Get columns for a specific table
     */
    abstract public function getColumns(string $tableName): array;

    /**
     * Get indexes for a specific table
     */
    abstract public function getIndexes(string $tableName): array;

    /**
     * Get foreign keys for a specific table
     */
    abstract public function getForeignKeys(string $tableName): array;

    /**
     * Get unique constraints for a specific table
     */
    abstract public function getUniqueConstraints(string $tableName): array;

    /**
     * Check for orphaned records in a foreign key relationship
     */
    abstract public function getOrphanedRecordsCount(string $table, string $column, string $referencedTable, string $referencedColumn): int;

    /**
     * Get database connection instance
     */
    protected function getConnection()
    {
        return DB::connection($this->connectionName);
    }

    /**
     * Get database name
     */
    protected function getDatabaseName(): string
    {
        return $this->getConnection()->getDatabaseName();
    }

    /**
     * Get driver name
     */
    public function getDriverName(): string
    {
        return $this->getConnection()->getDriverName();
    }

    /**
     * Create adapter instance based on database driver
     */
    public static function create(?string $connectionName = null): self
    {
        $connectionName = $connectionName ?? config('database.default');
        $driver = config("database.connections.{$connectionName}.driver");

        return match ($driver) {
            'mysql' => new MySqlAdapter($connectionName),
            'pgsql' => new PostgreSqlAdapter($connectionName),
            'sqlsrv' => new SqlServerAdapter($connectionName),
            'oracle' => new OracleAdapter($connectionName),
            'sqlite' => new SqliteAdapter($connectionName),
            default => throw new \InvalidArgumentException("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * Get list of common indexable columns
     */
    protected function getCommonIndexableColumns(): array
    {
        return [
            'email', 'uuid', 'status', 'type', 'code', 'slug', 'name',
            'active', 'enabled', 'published', 'deleted_at', 'created_at',
            'updated_at', 'sort_order', 'position',
        ];
    }

    /**
     * Get list of columns that should have unique constraints
     */
    protected function getUniqueColumns(): array
    {
        return [
            'email', 'uuid', 'slug', 'code', 'username',
        ];
    }

    /**
     * Check if a column should be considered for foreign key
     */
    protected function shouldHaveForeignKey(string $columnName): bool
    {
        return str_ends_with($columnName, '_id') &&
               $columnName !== 'id' &&
               ! in_array($columnName, ['created_by', 'updated_by', 'deleted_by']);
    }

    /**
     * Guess referenced table name from foreign key column
     */
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
}
