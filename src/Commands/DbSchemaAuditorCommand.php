<?php

namespace CleaniqueCoders\DbSchemaAuditor\Commands;

use CleaniqueCoders\DbSchemaAuditor\Actions\AnalyzeModelRelationshipsAction;
use CleaniqueCoders\DbSchemaAuditor\Actions\AuditDatabaseIntegrityAction;
use CleaniqueCoders\DbSchemaAuditor\Actions\GenerateFixMigrationsAction;
use CleaniqueCoders\DbSchemaAuditor\Actions\SaveAuditResultsToDatabaseAction;
use CleaniqueCoders\DbSchemaAuditor\Formatters\AbstractFormatter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DbSchemaAuditorCommand extends Command
{
    public $signature = 'db:audit
                        {--format=console : Output format (console, json, markdown, csv, html)}
                        {--path= : Output path for file formats}
                        {--connection= : Database connection to audit}
                        {--models : Include model relationship analysis}
                        {--generate-fixes : Generate migration fixes}
                        {--save-database : Save results to database}
                        {--config= : Custom config file path}';

    public $description = 'Audit database schema design and model relationships';

    public function handle(): int
    {
        $this->info('🔍 Starting Database Schema Audit...');
        $this->newLine();

        $format = $this->option('format');
        $outputPath = $this->option('path');
        $connection = $this->option('connection');
        $includeModels = $this->option('models');
        $generateFixes = $this->option('generate-fixes');
        $saveToDatabase = $this->option('save-database');

        // Load configuration
        $config = $this->loadConfiguration();

        try {
            // Run database integrity audit
            $this->info('📊 Auditing database integrity...');
            $auditResults = AuditDatabaseIntegrityAction::run($connection, $config);

            $combinedResults = $auditResults;

            // Run model relationship analysis if requested
            if ($includeModels) {
                $this->info('🔗 Analyzing model relationships...');
                $modelResults = AnalyzeModelRelationshipsAction::run();

                // Merge results
                $combinedResults['model_analysis'] = $modelResults;
                $combinedResults['issues'] = array_merge(
                    $combinedResults['issues'] ?? [],
                    $modelResults['issues'] ?? []
                );
            }

            // Generate fixes if requested
            if ($generateFixes) {
                $this->info('🔧 Generating fix migrations...');
                $fixResults = GenerateFixMigrationsAction::run($combinedResults);
                $combinedResults['fix_generation'] = $fixResults;

                if (! empty($fixResults['migration_files'])) {
                    $this->info('Generated '.count($fixResults['migration_files']).' migration files');
                }
            }

            // Save to database if requested
            if ($saveToDatabase) {
                $this->saveResultsToDatabase($combinedResults);
            }

            // Format and output results
            $this->outputResults($combinedResults, $format, $outputPath);

            $this->displaySummary($combinedResults);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Audit failed: '.$e->getMessage());

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    protected function loadConfiguration(): array
    {
        $configPath = $this->option('config');

        if ($configPath && File::exists($configPath)) {
            return include $configPath;
        }

        return config('db-schema-auditor', []);
    }

    protected function outputResults(array $results, string $format, ?string $outputPath): void
    {
        try {
            $formatter = AbstractFormatter::create($format, $results);
            $formattedOutput = $formatter->format();

            if ($format === 'console') {
                $this->line($formattedOutput);
            } else {
                $this->saveFormattedOutput($formattedOutput, $format, $outputPath, $results);
            }

        } catch (\Exception $e) {
            $this->error("Failed to format output: {$e->getMessage()}");

            // Fallback to console output
            if ($format !== 'console') {
                $this->warn('Falling back to console output...');
                $formatter = AbstractFormatter::create('console', $results);
                $this->line($formatter->format());
            }
        }
    }

    protected function saveFormattedOutput(string $output, string $format, ?string $outputPath, array $results): void
    {
        // Determine output path
        if (! $outputPath) {
            $outputPath = $this->getDefaultOutputPath($format);
        }

        // Ensure directory exists
        File::ensureDirectoryExists(dirname($outputPath));

        // Generate filename if path is a directory
        if (is_dir($outputPath)) {
            $outputPath = $this->generateOutputFilename($outputPath, $format);
        }

        // Save file
        File::put($outputPath, $output);

        $this->info("✅ Report saved to: {$outputPath}");

        // Display file size
        $size = File::size($outputPath);
        $this->line('   File size: '.$this->formatBytes($size));
    }

    protected function getDefaultOutputPath(string $format): string
    {
        $baseDir = database_path('audit');
        File::ensureDirectoryExists($baseDir);

        return $this->generateOutputFilename($baseDir, $format);
    }

    protected function generateOutputFilename(string $directory, string $format): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $extension = match ($format) {
            'json' => 'json',
            'markdown', 'md' => 'md',
            'csv' => 'csv',
            'html' => 'html',
            default => 'txt',
        };

        return "{$directory}/db_audit_{$timestamp}.{$extension}";
    }

    protected function saveResultsToDatabase(array $results): void
    {
        $this->info('💾 Saving results to database...');

        try {
            $audit = SaveAuditResultsToDatabaseAction::run($results);
            $this->info("✅ Results saved to database (Audit ID: {$audit->uuid})");
        } catch (\Exception $e) {
            $this->warn("Failed to save to database: {$e->getMessage()}");
        }
    }

    protected function displaySummary(array $results): void
    {
        $this->newLine();
        $this->info('📋 Audit Summary');
        $this->line(str_repeat('─', 50));

        $stats = $results['stats'] ?? [];
        $issues = $results['issues'] ?? [];

        // Database stats
        if (isset($stats['database_type'])) {
            $this->line("Database: <comment>{$stats['database_type']}</comment>");
        }

        if (isset($stats['total_tables'])) {
            $this->line("Tables scanned: <comment>{$stats['tables_scanned']}/{$stats['total_tables']}</comment>");
        }

        // Issue counts
        $totalIssues = array_sum(array_map('count', $issues));
        $issueColor = $totalIssues > 0 ? 'error' : 'info';
        $this->line("Total issues: <{$issueColor}>{$totalIssues}</{$issueColor}>");

        // Issues by type
        foreach ($issues as $type => $typeIssues) {
            if (! empty($typeIssues)) {
                $typeLabel = ucwords(str_replace('_', ' ', $type));
                $this->line("  • {$typeLabel}: <comment>".count($typeIssues).'</comment>');
            }
        }

        // Model analysis summary
        if (isset($results['model_analysis'])) {
            $modelStats = $results['model_analysis']['stats'] ?? [];
            if (isset($modelStats['models_analyzed'])) {
                $this->line("Models analyzed: <comment>{$modelStats['models_analyzed']}</comment>");
            }
        }

        // Fix generation summary
        if (isset($results['fix_generation'])) {
            $fixStats = $results['fix_generation'];
            if (isset($fixStats['migrations_created'])) {
                $this->line("Migrations created: <comment>{$fixStats['migrations_created']}</comment>");
            }
        }

        $this->newLine();

        // Next steps
        if ($totalIssues > 0) {
            $this->warn('⚠️  Issues found! Review the detailed report above.');
            $this->line('');
            $this->line('💡 <comment>Next steps:</comment>');
            $this->line('  1. Review high-severity issues first');
            $this->line('  2. Add missing indexes for performance');
            $this->line('  3. Implement missing constraints for data integrity');

            if (! $this->option('generate-fixes')) {
                $this->line('  4. Run with --generate-fixes to create migration files');
            }
        } else {
            $this->info('✅ No issues found! Your database schema looks great.');
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
