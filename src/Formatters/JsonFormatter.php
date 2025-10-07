<?php

namespace CleaniqueCoders\DbSchemaAuditor\Formatters;

class JsonFormatter extends AbstractFormatter
{
    public function format(): string
    {
        $output = [
            'audit_results' => $this->data,
            'summary' => $this->generateSummary(),
            'metadata' => $this->generateMetadata(),
        ];

        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

        if ($this->options['compact'] ?? false) {
            $flags = JSON_UNESCAPED_SLASHES;
        }

        return json_encode($output, $flags);
    }

    protected function generateSummary(): array
    {
        $issues = $this->getIssuesByType();
        $stats = $this->getStats();

        $summary = [
            'total_issues' => $this->getIssueCount(),
            'issues_by_type' => [],
            'issues_by_severity' => [
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ],
        ];

        foreach ($issues as $type => $typeIssues) {
            $summary['issues_by_type'][$type] = count($typeIssues);

            // Count by severity
            foreach ($typeIssues as $issue) {
                $severity = $issue['severity'] ?? 'medium';
                if (isset($summary['issues_by_severity'][$severity])) {
                    $summary['issues_by_severity'][$severity]++;
                }
            }
        }

        if (! empty($stats)) {
            $summary['database_stats'] = $stats;
        }

        return $summary;
    }

    protected function generateMetadata(): array
    {
        return [
            'generated_at' => now()->toISOString(),
            'format_version' => '1.0',
            'tool' => 'db-schema-auditor',
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }
}
