<?php

namespace CleaniqueCoders\DbSchemaAuditor\Formatters;

abstract class AbstractFormatter
{
    protected array $data;

    protected array $options;

    public function __construct(array $data, array $options = [])
    {
        $this->data = $data;
        $this->options = array_merge($this->getDefaultOptions(), $options);
    }

    abstract public function format(): string;

    protected function getDefaultOptions(): array
    {
        return [
            'include_stats' => true,
            'include_recommendations' => true,
            'group_by_severity' => false,
            'show_sql_examples' => false,
        ];
    }

    public static function create(string $format, array $data, array $options = []): self
    {
        return match (strtolower($format)) {
            'console' => new ConsoleFormatter($data, $options),
            'json' => new JsonFormatter($data, $options),
            'markdown', 'md' => new MarkdownFormatter($data, $options),
            'csv' => new CsvFormatter($data, $options),
            'html' => new HtmlFormatter($data, $options),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
        };
    }

    protected function getSeverityColor(string $severity): string
    {
        return match ($severity) {
            'high' => 'red',
            'medium' => 'yellow',
            'low' => 'blue',
            default => 'white',
        };
    }

    protected function getSeverityIcon(string $severity): string
    {
        return match ($severity) {
            'high' => '🔴',
            'medium' => '🟡',
            'low' => '🔵',
            default => '⚪',
        };
    }

    protected function formatNumber(int $number): string
    {
        return number_format($number);
    }

    protected function getIssueCount(): int
    {
        $issues = $this->data['issues'] ?? [];

        return array_sum(array_map('count', $issues));
    }

    protected function getIssuesByType(): array
    {
        return $this->data['issues'] ?? [];
    }

    protected function getStats(): array
    {
        return $this->data['stats'] ?? [];
    }

    protected function getAuditTime(): string
    {
        return $this->data['audit_time'] ?? now()->toISOString();
    }
}
