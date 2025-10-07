<?php

namespace CleaniqueCoders\DbSchemaAuditor\Actions;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateFixMigrationsAction
{
    use AsAction;

    public function handle(array $auditResults, ?string $outputPath = null): array
    {
        $outputPath = $outputPath ?? database_path('migrations');
        $issues = $auditResults['issues'] ?? [];

        $migrations = [];
        $timestamp = now();

        // Generate migrations for missing indexes
        if (! empty($issues['missing_indexes'])) {
            $indexMigrations = $this->generateIndexMigrations($issues['missing_indexes'], $timestamp);
            $migrations = array_merge($migrations, $indexMigrations);
        }

        // Generate migrations for missing unique constraints
        if (! empty($issues['missing_unique_constraints'])) {
            $uniqueMigrations = $this->generateUniqueConstraintMigrations($issues['missing_unique_constraints'], $timestamp);
            $migrations = array_merge($migrations, $uniqueMigrations);
        }

        // Generate migrations for missing foreign keys
        if (! empty($issues['missing_foreign_keys'])) {
            $foreignKeyMigrations = $this->generateForeignKeyMigrations($issues['missing_foreign_keys'], $timestamp);
            $migrations = array_merge($migrations, $foreignKeyMigrations);
        }

        // Create migration files
        $createdFiles = [];
        foreach ($migrations as $index => $migration) {
            $migrationTimestamp = $timestamp->copy()->addSeconds($index);
            $fileName = $this->createMigrationFile($migration, $migrationTimestamp, $outputPath);
            if ($fileName) {
                $createdFiles[] = $fileName;
            }
        }

        // Generate cleanup script for orphaned records
        $cleanupScript = null;
        if (! empty($issues['orphaned_records'])) {
            $cleanupScript = $this->generateOrphanedRecordsCleanupScript($issues['orphaned_records'], $outputPath);
        }

        return [
            'migrations_created' => count($createdFiles),
            'migration_files' => $createdFiles,
            'cleanup_script' => $cleanupScript,
            'summary' => $this->generateSummary($migrations, $issues),
        ];
    }

    protected function generateIndexMigrations(array $indexIssues, $timestamp): array
    {
        $migrations = [];
        $indexesByTable = collect($indexIssues)->groupBy('table');

        foreach ($indexesByTable as $table => $tableIssues) {
            $upStatements = [];
            $downStatements = [];

            foreach ($tableIssues as $issue) {
                $column = $issue['column'];
                $indexName = "idx_{$table}_{$column}";

                $upStatements[] = "            \$table->index('{$column}', '{$indexName}');";
                $downStatements[] = "            \$table->dropIndex('{$indexName}');";
            }

            $migrations[] = [
                'type' => 'indexes',
                'table' => $table,
                'name' => "add_indexes_to_{$table}_table",
                'up' => implode("\n", $upStatements),
                'down' => implode("\n", $downStatements),
                'description' => "Add indexes to {$table} table for performance optimization",
            ];
        }

        return $migrations;
    }

    protected function generateUniqueConstraintMigrations(array $uniqueIssues, $timestamp): array
    {
        $migrations = [];
        $uniquesByTable = collect($uniqueIssues)->groupBy('table');

        foreach ($uniquesByTable as $table => $tableIssues) {
            $upStatements = [];
            $downStatements = [];

            foreach ($tableIssues as $issue) {
                $column = $issue['column'];
                $uniqueName = "unique_{$table}_{$column}";

                $upStatements[] = "            \$table->unique('{$column}', '{$uniqueName}');";
                $downStatements[] = "            \$table->dropUnique('{$uniqueName}');";
            }

            $migrations[] = [
                'type' => 'unique_constraints',
                'table' => $table,
                'name' => "add_unique_constraints_to_{$table}_table",
                'up' => implode("\n", $upStatements),
                'down' => implode("\n", $downStatements),
                'description' => "Add unique constraints to {$table} table",
            ];
        }

        return $migrations;
    }

    protected function generateForeignKeyMigrations(array $foreignKeyIssues, $timestamp): array
    {
        $migrations = [];
        $foreignKeysByTable = collect($foreignKeyIssues)->groupBy('table');

        foreach ($foreignKeysByTable as $table => $tableIssues) {
            $upStatements = [];
            $downStatements = [];

            foreach ($tableIssues as $issue) {
                $column = $issue['column'];
                $referencedTable = $issue['guessed_reference'] ?? $this->guessReferencedTable($column);
                $fkName = "fk_{$table}_{$column}";

                $upStatements[] = "            // \$table->foreign('{$column}', '{$fkName}')->references('id')->on('{$referencedTable}')->onDelete('cascade');";
                $downStatements[] = "            // \$table->dropForeign('{$fkName}');";
            }

            $migrations[] = [
                'type' => 'foreign_keys',
                'table' => $table,
                'name' => "add_foreign_keys_to_{$table}_table",
                'up' => implode("\n", $upStatements),
                'down' => implode("\n", $downStatements),
                'description' => "Add foreign key constraints to {$table} table",
                'note' => 'Foreign key suggestions are commented out. Please review and uncomment after verifying referenced tables.',
            ];
        }

        return $migrations;
    }

    protected function createMigrationFile(array $migration, $timestamp, string $outputPath): ?string
    {
        $migrationName = $timestamp->format('Y_m_d_His').'_'.$migration['name'].'.php';
        $migrationPath = $outputPath.'/'.$migrationName;

        $className = Str::studly($migration['name']);

        $content = $this->generateMigrationContent($migration, $className);

        try {
            File::put($migrationPath, $content);

            return $migrationName;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function generateMigrationContent(array $migration, string $className): string
    {
        $note = isset($migration['note']) ? "\n     * NOTE: {$migration['note']}" : '';

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * {$migration['description']}{$note}
     */
    public function up(): void
    {
        Schema::table('{$migration['table']}', function (Blueprint \$table) {
{$migration['up']}
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('{$migration['table']}', function (Blueprint \$table) {
{$migration['down']}
        });
    }
};
PHP;
    }

    protected function generateOrphanedRecordsCleanupScript(array $orphanedIssues, string $outputPath): ?string
    {
        $timestamp = now()->format('Ymd_His');
        $scriptPath = dirname($outputPath).'/audit/'.$timestamp.'_cleanup_orphaned_records.php';

        // Ensure audit directory exists
        File::ensureDirectoryExists(dirname($scriptPath));

        $content = $this->generateCleanupScriptContent($orphanedIssues);

        try {
            File::put($scriptPath, $content);

            return basename($scriptPath);
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function generateCleanupScriptContent(array $orphanedIssues): string
    {
        $content = <<<'PHP'
<?php

/**
 * Cleanup Orphaned Records Script
 *
 * WARNING: This script will DELETE orphaned records.
 * Review carefully before running!
 *
 * Usage: php artisan tinker < database/audit/SCRIPT_NAME.php
 */

use Illuminate\Support\Facades\DB;

echo "\n";
echo "========================================\n";
echo "ORPHANED RECORDS CLEANUP\n";
echo "========================================\n";
echo "WARNING: This will delete orphaned records!\n\n";

// Backup recommendation
echo "IMPORTANT: Create a database backup before running this script!\n\n";

$deletedCounts = [];

PHP;

        foreach ($orphanedIssues as $issue) {
            $table = $issue['table'];
            $column = $issue['column'];
            $referencedTable = $issue['referenced_table'];
            $referencedColumn = $issue['referenced_column'];
            $count = $issue['count'];

            $content .= <<<PHP

echo "Cleaning {$table}.{$column} (referencing {$referencedTable}.{$referencedColumn}) - {$count} records...\\n";
// Uncomment the following lines to perform the cleanup:
/*
\$deleted = DB::table('{$table}')
    ->whereNotNull('{$column}')
    ->whereNotExists(function(\$query) {
        \$query->select(DB::raw(1))
               ->from('{$referencedTable}')
               ->whereColumn('{$table}.{$column}', '=', '{$referencedTable}.{$referencedColumn}');
    })
    ->delete();

\$deletedCounts['{$table}.{$column}'] = \$deleted;
echo "  Deleted: \$deleted records\\n";
*/
echo "  Skipped (commented out)\\n";

PHP;
        }

        $content .= <<<'PHP'

echo "\n========================================\n";
echo "CLEANUP SUMMARY\n";
echo "========================================\n";

if (empty($deletedCounts)) {
    echo "No records were deleted (all operations are commented out).\n";
    echo "Review the script and uncomment the deletion statements to proceed.\n";
} else {
    foreach ($deletedCounts as $location => $count) {
        echo "• {$location}: {$count} records deleted\n";
    }
}

echo "\nDone!\n";
PHP;

        return $content;
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

    protected function generateSummary(array $migrations, array $issues): array
    {
        return [
            'migrations_by_type' => collect($migrations)->countBy('type')->toArray(),
            'issues_by_severity' => [
                'high' => collect($issues)->flatten(1)->where('severity', 'high')->count(),
                'medium' => collect($issues)->flatten(1)->where('severity', 'medium')->count(),
                'low' => collect($issues)->flatten(1)->where('severity', 'low')->count(),
            ],
            'next_steps' => [
                'Review generated migration files in database/migrations/',
                'Uncomment foreign key constraints after verifying referenced tables',
                'Run migrations: php artisan migrate',
                'Review cleanup script for orphaned records if generated',
                'Update models with missing relationships as suggested',
            ],
        ];
    }
}
