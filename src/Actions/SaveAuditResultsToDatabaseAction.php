<?php

namespace CleaniqueCoders\DbSchemaAuditor\Actions;

use CleaniqueCoders\DbSchemaAuditor\Models\DbAudit;
use CleaniqueCoders\DbSchemaAuditor\Models\DbAuditIssue;
use CleaniqueCoders\DbSchemaAuditor\Models\DbAuditModelAnalysis;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class SaveAuditResultsToDatabaseAction
{
    use AsAction;

    public function handle(array $auditResults): DbAudit
    {
        $stats = $auditResults['stats'] ?? [];
        $issues = $auditResults['issues'] ?? [];
        $modelAnalysis = $auditResults['model_analysis'] ?? null;

        // Create main audit record
        $audit = DbAudit::create([
            'uuid' => Str::uuid(),
            'connection_name' => $stats['connection'] ?? config('database.default'),
            'database_type' => $stats['database_type'] ?? 'unknown',
            'total_tables' => $stats['total_tables'] ?? 0,
            'tables_scanned' => $stats['tables_scanned'] ?? 0,
            'total_issues' => $stats['issues_found'] ?? 0,
            'audit_config' => $this->sanitizeConfig($auditResults),
            'execution_time' => $this->calculateExecutionTime($auditResults),
            'status' => 'completed',
            'started_at' => $this->getStartTime($auditResults),
            'completed_at' => now(),
        ]);

        // Save individual issues
        $this->saveIssues($audit, $issues);

        // Save model analysis if available
        if ($modelAnalysis) {
            $this->saveModelAnalysis($audit, $modelAnalysis);
        }

        return $audit;
    }

    protected function saveIssues(DbAudit $audit, array $issues): void
    {
        foreach ($issues as $type => $typeIssues) {
            foreach ($typeIssues as $issue) {
                DbAuditIssue::create([
                    'uuid' => Str::uuid(),
                    'audit_id' => $audit->id,
                    'issue_type' => $type,
                    'table_name' => $issue['table'] ?? '',
                    'column_name' => $issue['column'] ?? null,
                    'severity' => $issue['severity'] ?? 'medium',
                    'recommendation' => $issue['recommendation'] ?? null,
                    'details' => $this->prepareIssueDetails($issue),
                    'count' => $issue['count'] ?? null,
                    'referenced_table' => $issue['referenced_table'] ?? null,
                    'referenced_column' => $issue['referenced_column'] ?? null,
                    'fix_generated' => false,
                    'fix_applied' => false,
                ]);
            }
        }
    }

    protected function saveModelAnalysis(DbAudit $audit, array $modelAnalysis): void
    {
        $models = $modelAnalysis['models'] ?? [];

        foreach ($models as $modelClass => $analysis) {
            DbAuditModelAnalysis::create([
                'uuid' => Str::uuid(),
                'audit_id' => $audit->id,
                'model_class' => $modelClass,
                'table_name' => $analysis['table'] ?? '',
                'relationships_found' => count($analysis['relationships'] ?? []),
                'relationships_data' => $analysis['relationships'] ?? [],
                'issues_found' => $this->countModelIssues($modelClass, $modelAnalysis['issues'] ?? []),
                'issues_data' => $this->getModelIssues($modelClass, $modelAnalysis['issues'] ?? []),
                'file_path' => $analysis['file'] ?? null,
            ]);
        }
    }

    protected function prepareIssueDetails(array $issue): array
    {
        $details = [];

        // Add issue-specific details
        if (isset($issue['type'])) {
            $details['type'] = $issue['type'];
        }

        if (isset($issue['guessed_reference'])) {
            $details['guessed_reference'] = $issue['guessed_reference'];
        }

        if (isset($issue['issue'])) {
            $details['issue'] = $issue['issue'];
        }

        // Add any other metadata
        foreach ($issue as $key => $value) {
            if (! in_array($key, [
                'table', 'column', 'severity', 'recommendation',
                'count', 'referenced_table', 'referenced_column',
            ]) && ! is_array($value)) {
                $details[$key] = $value;
            }
        }

        return $details;
    }

    protected function countModelIssues(string $modelClass, array $issues): int
    {
        $count = 0;

        foreach ($issues as $issueType => $typeIssues) {
            foreach ($typeIssues as $issue) {
                if (($issue['model'] ?? '') === $modelClass) {
                    $count++;
                }
            }
        }

        return $count;
    }

    protected function getModelIssues(string $modelClass, array $issues): array
    {
        $modelIssues = [];

        foreach ($issues as $issueType => $typeIssues) {
            $modelTypeIssues = collect($typeIssues)
                ->filter(fn ($issue) => ($issue['model'] ?? '') === $modelClass)
                ->values()
                ->toArray();

            if (! empty($modelTypeIssues)) {
                $modelIssues[$issueType] = $modelTypeIssues;
            }
        }

        return $modelIssues;
    }

    protected function sanitizeConfig(array $auditResults): array
    {
        // Return only configuration-related data, not the full results
        return [
            'audit_time' => $auditResults['audit_time'] ?? now()->toISOString(),
            'options' => $auditResults['options'] ?? [],
            'version' => '1.0',
        ];
    }

    protected function calculateExecutionTime(array $auditResults): float
    {
        // This would ideally come from timing the actual audit process
        // For now, we'll estimate based on the number of tables and issues
        $stats = $auditResults['stats'] ?? [];
        $tablesScanned = $stats['tables_scanned'] ?? 0;
        $issuesFound = $stats['issues_found'] ?? 0;

        // Rough estimation: 0.1 seconds per table + 0.01 seconds per issue
        return round(($tablesScanned * 0.1) + ($issuesFound * 0.01), 2);
    }

    protected function getStartTime(array $auditResults): \Carbon\Carbon
    {
        // If we have audit time, estimate start time
        if (isset($auditResults['audit_time'])) {
            $auditTime = \Carbon\Carbon::parse($auditResults['audit_time']);
            $executionTime = $this->calculateExecutionTime($auditResults);

            return $auditTime->subSeconds($executionTime);
        }

        return now()->subMinute(); // Default to 1 minute ago
    }
}
