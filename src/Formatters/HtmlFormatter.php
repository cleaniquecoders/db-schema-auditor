<?php

namespace CleaniqueCoders\DbSchemaAuditor\Formatters;

use Illuminate\Support\Str;

class HtmlFormatter extends AbstractFormatter
{
    public function format(): string
    {
        $output = [];

        $output[] = $this->getHtmlHeader();
        $output[] = $this->getHtmlStyles();
        $output[] = '<body>';
        $output[] = '<div class="container">';

        // Header
        $output[] = '<header class="header">';
        $output[] = '<h1>🔍 Database Schema Audit Report</h1>';
        $output[] = '<p class="timestamp">Generated: '.$this->getAuditTime().'</p>';
        $output[] = '</header>';

        // Stats
        if ($this->options['include_stats']) {
            $output[] = $this->formatStats();
        }

        // Issues
        $issueCount = $this->getIssueCount();
        if ($issueCount > 0) {
            $output[] = $this->formatIssuesSummary();
            $output[] = $this->formatIssuesDetails();
        } else {
            $output[] = '<div class="success-message">';
            $output[] = '<h2>✅ No Issues Found!</h2>';
            $output[] = '<p>Your database schema looks great.</p>';
            $output[] = '</div>';
        }

        $output[] = '</div>';
        $output[] = '</body>';
        $output[] = '</html>';

        return implode("\n", $output);
    }

    protected function getHtmlHeader(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Schema Audit Report</title>
HTML;
    }

    protected function getHtmlStyles(): string
    {
        return <<<'HTML'
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .timestamp {
            color: #6c757d;
            font-size: 0.9em;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
            border-left: 4px solid #007bff;
        }
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9em;
        }
        .severity-high { color: #dc3545; }
        .severity-medium { color: #ffc107; }
        .severity-low { color: #17a2b8; }
        .issue-type {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .issue-type-header {
            background: #e9ecef;
            padding: 15px;
            font-weight: bold;
            border-bottom: 1px solid #dee2e6;
        }
        .issue-item {
            padding: 15px;
            border-bottom: 1px solid #f8f9fa;
        }
        .issue-item:last-child {
            border-bottom: none;
        }
        .issue-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .issue-details {
            font-size: 0.9em;
            color: #6c757d;
        }
        .recommendation {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 10px;
            margin-top: 10px;
            border-radius: 4px;
        }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .badge-high { background: #dc3545; color: white; }
        .badge-medium { background: #ffc107; color: #212529; }
        .badge-low { background: #17a2b8; color: white; }
        .success-message {
            text-align: center;
            padding: 40px;
            background: #d4edda;
            border-radius: 6px;
            color: #155724;
        }
        .table-name {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
        }
        .column-name {
            font-family: monospace;
            background: #fff3cd;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
</head>
HTML;
    }

    protected function formatStats(): string
    {
        $stats = $this->getStats();
        $output = [];

        $output[] = '<div class="stats-section">';
        $output[] = '<h2>📊 Audit Statistics</h2>';
        $output[] = '<div class="stats-grid">';

        if (isset($stats['database_type'])) {
            $output[] = '<div class="stat-card">';
            $output[] = '<div class="stat-value">'.strtoupper($stats['database_type']).'</div>';
            $output[] = '<div class="stat-label">Database Type</div>';
            $output[] = '</div>';
        }

        if (isset($stats['total_tables'])) {
            $output[] = '<div class="stat-card">';
            $output[] = '<div class="stat-value">'.$this->formatNumber($stats['tables_scanned']).'</div>';
            $output[] = '<div class="stat-label">Tables Scanned</div>';
            $output[] = '</div>';
        }

        if (isset($stats['issues_found'])) {
            $output[] = '<div class="stat-card">';
            $color = $stats['issues_found'] > 0 ? '#dc3545' : '#28a745';
            $output[] = '<div class="stat-value" style="color: '.$color.'">'.$this->formatNumber($stats['issues_found']).'</div>';
            $output[] = '<div class="stat-label">Issues Found</div>';
            $output[] = '</div>';
        }

        $output[] = '</div>';
        $output[] = '</div>';

        return implode("\n", $output);
    }

    protected function formatIssuesSummary(): string
    {
        $issues = $this->getIssuesByType();
        $output = [];

        $output[] = '<div class="issues-summary">';
        $output[] = '<h2>⚠️ Issues by Type</h2>';
        $output[] = '<div class="stats-grid">';

        foreach ($issues as $type => $typeIssues) {
            if (empty($typeIssues)) {
                continue;
            }

            $typeLabel = $this->formatIssueTypeLabel($type);
            $count = count($typeIssues);

            $output[] = '<div class="stat-card">';
            $output[] = '<div class="stat-value severity-high">'.$count.'</div>';
            $output[] = '<div class="stat-label">'.htmlspecialchars($typeLabel).'</div>';
            $output[] = '</div>';
        }

        $output[] = '</div>';
        $output[] = '</div>';

        return implode("\n", $output);
    }

    protected function formatIssuesDetails(): string
    {
        $issues = $this->getIssuesByType();
        $output = [];

        $output[] = '<div class="issues-details">';
        $output[] = '<h2>🔍 Detailed Issues</h2>';

        foreach ($issues as $type => $typeIssues) {
            if (empty($typeIssues)) {
                continue;
            }

            $output[] = $this->formatIssueTypeSection($type, $typeIssues);
        }

        $output[] = '</div>';

        return implode("\n", $output);
    }

    protected function formatIssueTypeSection(string $type, array $typeIssues): string
    {
        $output = [];
        $typeLabel = $this->formatIssueTypeLabel($type);
        $count = count($typeIssues);

        $output[] = '<div class="issue-type">';
        $output[] = '<div class="issue-type-header">';
        $output[] = htmlspecialchars($typeLabel).' ('.$count.' issues)';
        $output[] = '</div>';

        foreach ($typeIssues as $issue) {
            $output[] = $this->formatSingleIssue($issue);
        }

        $output[] = '</div>';

        return implode("\n", $output);
    }

    protected function formatSingleIssue(array $issue): string
    {
        $output = [];

        $severity = $issue['severity'] ?? 'medium';
        $table = htmlspecialchars($issue['table'] ?? 'N/A');
        $column = htmlspecialchars($issue['column'] ?? 'N/A');

        $output[] = '<div class="issue-item">';
        $output[] = '<div class="issue-main">';
        $output[] = '<div>';
        $output[] = '<span class="table-name">'.$table.'</span>.<span class="column-name">'.$column.'</span>';
        $output[] = '</div>';
        $output[] = '<span class="badge badge-'.$severity.'">'.strtoupper($severity).'</span>';
        $output[] = '</div>';

        // Details
        $details = [];
        if (isset($issue['type'])) {
            $details[] = '<strong>Type:</strong> '.htmlspecialchars($issue['type']);
        }
        if (isset($issue['count'])) {
            $details[] = '<strong>Count:</strong> '.$this->formatNumber($issue['count']);
        }
        if (isset($issue['referenced_table'])) {
            $details[] = '<strong>References:</strong> <span class="table-name">'.htmlspecialchars($issue['referenced_table']).'</span>';
        }

        if (! empty($details)) {
            $output[] = '<div class="issue-details">'.implode(' | ', $details).'</div>';
        }

        // Recommendation
        if ($this->options['include_recommendations'] && isset($issue['recommendation'])) {
            $output[] = '<div class="recommendation">';
            $output[] = '<strong>💡 Recommendation:</strong> '.htmlspecialchars($issue['recommendation']);
            $output[] = '</div>';
        }

        $output[] = '</div>';

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
