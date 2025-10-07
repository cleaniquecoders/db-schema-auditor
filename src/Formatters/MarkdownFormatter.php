<?php

namespace CleaniqueCoders\DbSchemaAuditor\Formatters;

use Illuminate\Support\Str;

class MarkdownFormatter extends AbstractFormatter
{
    public function format(): string
    {
        $output = [];

        // Header
        $output[] = '# Database Schema Audit Report';
        $output[] = '';

        // Stats
        if ($this->options['include_stats']) {
            $output[] = $this->formatStats();
        }

        // Table of Contents
        $output[] = $this->formatTableOfContents();

        // Issues
        $issueCount = $this->getIssueCount();
        if ($issueCount > 0) {
            $output[] = '## Issues Found';
            $output[] = '';
            $output[] = $this->formatIssuesSummary();
            $output[] = $this->formatIssuesDetails();
        } else {
            $output[] = '## ✅ Results';
            $output[] = '';
            $output[] = '**No issues found!** Your database schema looks great.';
            $output[] = '';
        }

        // Recommendations
        if ($this->options['include_recommendations'] && $issueCount > 0) {
            $output[] = $this->formatRecommendations();
        }

        return implode("\n", $output);
    }

    protected function formatStats(): string
    {
        $stats = $this->getStats();
        $output = [];

        $output[] = '## 📊 Audit Statistics';
        $output[] = '';
        $output[] = '| Metric | Value |';
        $output[] = '|--------|-------|';

        if (isset($stats['database_type'])) {
            $output[] = "| Database Type | `{$stats['database_type']}` |";
        }

        if (isset($stats['connection'])) {
            $output[] = "| Connection | `{$stats['connection']}` |";
        }

        if (isset($stats['total_tables'])) {
            $output[] = "| Tables Scanned | {$this->formatNumber($stats['tables_scanned'])} / {$this->formatNumber($stats['total_tables'])} |";
        }

        if (isset($stats['issues_found'])) {
            $badge = $stats['issues_found'] > 0 ? '🔴' : '✅';
            $output[] = "| Issues Found | {$badge} {$this->formatNumber($stats['issues_found'])} |";
        }

        $output[] = "| Audit Time | {$this->getAuditTime()} |";
        $output[] = '';

        return implode("\n", $output);
    }

    protected function formatTableOfContents(): string
    {
        $issues = $this->getIssuesByType();
        $output = [];

        if (empty($issues)) {
            return '';
        }

        $output[] = '## 📑 Table of Contents';
        $output[] = '';

        foreach ($issues as $type => $typeIssues) {
            if (empty($typeIssues)) {
                continue;
            }

            $typeLabel = $this->formatIssueTypeLabel($type);
            $count = count($typeIssues);
            $anchor = strtolower(str_replace(' ', '-', $typeLabel));

            $output[] = "- [{$typeLabel}](#{$anchor}) ({$count} issues)";
        }

        $output[] = '';

        return implode("\n", $output);
    }

    protected function formatIssuesSummary(): string
    {
        $issues = $this->getIssuesByType();
        $output = [];

        $output[] = '### Summary by Type';
        $output[] = '';
        $output[] = '| Issue Type | Count | High | Medium | Low |';
        $output[] = '|------------|-------|------|--------|-----|';

        foreach ($issues as $type => $typeIssues) {
            if (empty($typeIssues)) {
                continue;
            }

            $typeLabel = $this->formatIssueTypeLabel($type);
            $total = count($typeIssues);

            $severityCounts = [
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ];

            foreach ($typeIssues as $issue) {
                $severity = $issue['severity'] ?? 'medium';
                if (isset($severityCounts[$severity])) {
                    $severityCounts[$severity]++;
                }
            }

            $output[] = "| {$typeLabel} | **{$total}** | {$severityCounts['high']} | {$severityCounts['medium']} | {$severityCounts['low']} |";
        }

        $output[] = '';

        return implode("\n", $output);
    }

    protected function formatIssuesDetails(): string
    {
        $issues = $this->getIssuesByType();
        $output = [];

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

        $output[] = "### {$typeLabel}";
        $output[] = '';
        $output[] = "_Found {$count} issue(s) of this type._";
        $output[] = '';

        // Group by table for better readability
        $issuesByTable = collect($typeIssues)->groupBy('table');

        foreach ($issuesByTable as $table => $tableIssues) {
            $output[] = "#### Table: `{$table}`";
            $output[] = '';

            foreach ($tableIssues as $issue) {
                $output[] = $this->formatSingleIssue($issue);
            }

            $output[] = '';
        }

        return implode("\n", $output);
    }

    protected function formatSingleIssue(array $issue): string
    {
        $output = [];

        $severity = $issue['severity'] ?? 'medium';
        $severityBadge = match ($severity) {
            'high' => '🔴 **HIGH**',
            'medium' => '🟡 **MEDIUM**',
            'low' => '🔵 **LOW**',
            default => '⚪ **UNKNOWN**',
        };

        $column = $issue['column'] ?? 'N/A';

        $output[] = "- **Column:** `{$column}` | **Severity:** {$severityBadge}";

        // Additional details
        $details = [];
        if (isset($issue['type'])) {
            $details[] = "**Type:** {$issue['type']}";
        }

        if (isset($issue['count'])) {
            $details[] = "**Count:** {$this->formatNumber($issue['count'])}";
        }

        if (isset($issue['referenced_table'])) {
            $details[] = "**References:** `{$issue['referenced_table']}`";
        }

        if (! empty($details)) {
            $output[] = '  '.implode(' | ', $details);
        }

        // Recommendation
        if ($this->options['include_recommendations'] && isset($issue['recommendation'])) {
            $output[] = "  > 💡 **Recommendation:** {$issue['recommendation']}";
        }

        return implode("\n", $output);
    }

    protected function formatRecommendations(): string
    {
        $output = [];
        $output[] = '## 📋 General Recommendations';
        $output[] = '';
        $output[] = '### Next Steps';
        $output[] = '';
        $output[] = '1. **Review Issues by Priority:**';
        $output[] = '   - Start with 🔴 **HIGH** severity issues';
        $output[] = '   - Address 🟡 **MEDIUM** severity issues for performance';
        $output[] = '   - Consider 🔵 **LOW** severity issues for best practices';
        $output[] = '';
        $output[] = '2. **Database Optimization:**';
        $output[] = '   - Add missing indexes for frequently queried columns';
        $output[] = '   - Implement unique constraints to prevent data duplicates';
        $output[] = '   - Add foreign key constraints for data integrity';
        $output[] = '';
        $output[] = '3. **Data Cleanup:**';
        $output[] = '   - Clean up orphaned records (if any)';
        $output[] = '   - Verify relationships in your models';
        $output[] = '';
        $output[] = '4. **Monitoring:**';
        $output[] = '   - Run this audit regularly';
        $output[] = '   - Monitor query performance after adding indexes';
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
