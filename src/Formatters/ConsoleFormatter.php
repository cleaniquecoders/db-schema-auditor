<?php

namespace CleaniqueCoders\DbSchemaAuditor\Formatters;

use Illuminate\Support\Str;

class ConsoleFormatter extends AbstractFormatter
{
    public function format(): string
    {
        $output = [];

        // Header
        $output[] = '';
        $output[] = '╔════════════════════════════════════════╗';
        $output[] = '║        DATABASE SCHEMA AUDIT           ║';
        $output[] = '╚════════════════════════════════════════╝';
        $output[] = '';

        // Stats
        if ($this->options['include_stats']) {
            $output[] = $this->formatStats();
        }

        // Issues summary
        $issueCount = $this->getIssueCount();
        if ($issueCount > 0) {
            $output[] = $this->formatIssuesSummary();
            $output[] = $this->formatIssuesDetails();
        } else {
            $output[] = '✅ <fg=green>No issues found! Your database schema looks great.</fg=green>';
            $output[] = '';
        }

        return implode("\n", $output);
    }

    protected function formatStats(): string
    {
        $stats = $this->getStats();
        $output = [];

        $output[] = '📊 <fg=cyan>AUDIT STATISTICS</fg=cyan>';
        $output[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';

        if (isset($stats['database_type'])) {
            $output[] = "Database Type: <fg=yellow>{$stats['database_type']}</fg=yellow>";
        }

        if (isset($stats['connection'])) {
            $output[] = "Connection: <fg=yellow>{$stats['connection']}</fg=yellow>";
        }

        if (isset($stats['total_tables'])) {
            $output[] = "Tables Scanned: <fg=yellow>{$this->formatNumber($stats['tables_scanned'])} / {$this->formatNumber($stats['total_tables'])}</fg=yellow>";
        }

        if (isset($stats['issues_found'])) {
            $issueColor = $stats['issues_found'] > 0 ? 'red' : 'green';
            $output[] = "Issues Found: <fg={$issueColor}>{$this->formatNumber($stats['issues_found'])}</fg={$issueColor}>";
        }

        $output[] = "Audit Time: <fg=yellow>{$this->getAuditTime()}</fg=yellow>";
        $output[] = '';

        return implode("\n", $output);
    }

    protected function formatIssuesSummary(): string
    {
        $issues = $this->getIssuesByType();
        $output = [];

        $output[] = '⚠️  <fg=red>ISSUES SUMMARY</fg=red>';
        $output[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';

        foreach ($issues as $type => $typeIssues) {
            if (empty($typeIssues)) {
                continue;
            }

            $count = count($typeIssues);
            $typeLabel = $this->formatIssueTypeLabel($type);

            // Group by severity if option is enabled
            if ($this->options['group_by_severity']) {
                $severityGroups = collect($typeIssues)->groupBy('severity');
                $severityInfo = [];
                foreach (['high', 'medium', 'low'] as $severity) {
                    if (isset($severityGroups[$severity])) {
                        $severityCount = count($severityGroups[$severity]);
                        $icon = $this->getSeverityIcon($severity);
                        $severityInfo[] = "{$icon} {$severityCount}";
                    }
                }
                $output[] = "• <fg=yellow>{$typeLabel}</fg=yellow>: {$count} total (".implode(', ', $severityInfo).')';
            } else {
                $output[] = "• <fg=yellow>{$typeLabel}</fg=yellow>: <fg=red>{$count}</fg=red>";
            }
        }

        $output[] = '';

        return implode("\n", $output);
    }

    protected function formatIssuesDetails(): string
    {
        $issues = $this->getIssuesByType();
        $output = [];

        $output[] = '🔍 <fg=cyan>DETAILED ISSUES</fg=cyan>';
        $output[] = '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━';
        $output[] = '';

        foreach ($issues as $type => $typeIssues) {
            if (empty($typeIssues)) {
                continue;
            }

            $output[] = $this->formatIssueTypeSection($type, $typeIssues);
        }

        return implode("\n", $output);
    }

    protected function formatIssueTypeSection(string $type, array $typeIssues): string
    {
        $output = [];
        $typeLabel = $this->formatIssueTypeLabel($type);
        $count = count($typeIssues);

        $output[] = "<fg=magenta>▶ {$typeLabel} ({$count})</fg=magenta>";
        $output[] = str_repeat('─', 50);

        foreach ($typeIssues as $issue) {
            $output[] = $this->formatSingleIssue($issue);
        }

        $output[] = '';

        return implode("\n", $output);
    }

    protected function formatSingleIssue(array $issue): string
    {
        $output = [];

        $severity = $issue['severity'] ?? 'medium';
        $icon = $this->getSeverityIcon($severity);
        $color = $this->getSeverityColor($severity);

        // Main issue line
        $table = $issue['table'] ?? 'N/A';
        $column = $issue['column'] ?? 'N/A';

        $output[] = "  {$icon} <fg={$color}>{$table}.{$column}</fg={$color}>";

        // Additional details
        if (isset($issue['type'])) {
            $output[] = "    Type: <fg=white>{$issue['type']}</fg=white>";
        }

        if (isset($issue['count'])) {
            $output[] = "    Count: <fg=white>{$this->formatNumber($issue['count'])}</fg=white>";
        }

        if (isset($issue['referenced_table'])) {
            $output[] = "    References: <fg=white>{$issue['referenced_table']}</fg=white>";
        }

        // Recommendation
        if ($this->options['include_recommendations'] && isset($issue['recommendation'])) {
            $output[] = "    💡 <fg=green>{$issue['recommendation']}</fg=green>";
        }

        $output[] = '';

        return implode("\n", $output);
    }

    protected function formatIssueTypeLabel(string $type): string
    {
        return match ($type) {
            'missing_indexes' => 'Missing Indexes',
            'missing_unique_constraints' => 'Missing Unique Constraints',
            'missing_foreign_keys' => 'Missing Foreign Keys',
            'orphaned_records' => 'Orphaned Records',
            'suspicious_columns' => 'Suspicious Columns',
            'missing_inverse_relationships' => 'Missing Inverse Relationships',
            'naming_inconsistencies' => 'Naming Inconsistencies',
            'potential_missing_relationships' => 'Potential Missing Relationships',
            'relationship_method_issues' => 'Relationship Method Issues',
            default => Str::title(str_replace('_', ' ', $type)),
        };
    }
}
