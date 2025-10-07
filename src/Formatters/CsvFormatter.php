<?php

namespace CleaniqueCoders\DbSchemaAuditor\Formatters;

class CsvFormatter extends AbstractFormatter
{
    public function format(): string
    {
        $rows = [];

        // Header row
        $rows[] = [
            'issue_type',
            'table',
            'column',
            'severity',
            'recommendation',
            'details',
            'count',
            'referenced_table',
            'audit_time',
        ];

        // Data rows
        $issues = $this->getIssuesByType();
        $auditTime = $this->getAuditTime();

        foreach ($issues as $type => $typeIssues) {
            foreach ($typeIssues as $issue) {
                $rows[] = [
                    $type,
                    $issue['table'] ?? '',
                    $issue['column'] ?? '',
                    $issue['severity'] ?? 'medium',
                    $issue['recommendation'] ?? '',
                    $issue['type'] ?? '',
                    $issue['count'] ?? '',
                    $issue['referenced_table'] ?? '',
                    $auditTime,
                ];
            }
        }

        return $this->arrayToCsv($rows);
    }

    protected function arrayToCsv(array $rows): string
    {
        $output = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
