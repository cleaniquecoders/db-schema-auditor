<?php

namespace CleaniqueCoders\DbSchemaAuditor\Tests\Unit;

use CleaniqueCoders\DbSchemaAuditor\Actions\AuditDatabaseIntegrityAction;
use CleaniqueCoders\DbSchemaAuditor\Adapters\AbstractDatabaseAdapter;
use CleaniqueCoders\DbSchemaAuditor\Formatters\AbstractFormatter;
use Orchestra\Testbench\TestCase;

class DatabaseAuditTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            \CleaniqueCoders\DbSchemaAuditor\DbSchemaAuditorServiceProvider::class,
        ];
    }

    /** @test */
    public function it_can_create_database_adapters_for_different_drivers()
    {
        $this->assertInstanceOf(
            \CleaniqueCoders\DbSchemaAuditor\Adapters\MySqlAdapter::class,
            AbstractDatabaseAdapter::create('testing')
        );
    }

    /** @test */
    public function it_can_create_formatters_for_different_output_types()
    {
        $sampleData = [
            'issues' => [],
            'stats' => ['total_tables' => 5, 'issues_found' => 0],
            'audit_time' => now()->toISOString(),
        ];

        $consoleFormatter = AbstractFormatter::create('console', $sampleData);
        $this->assertInstanceOf(
            \CleaniqueCoders\DbSchemaAuditor\Formatters\ConsoleFormatter::class,
            $consoleFormatter
        );

        $jsonFormatter = AbstractFormatter::create('json', $sampleData);
        $this->assertInstanceOf(
            \CleaniqueCoders\DbSchemaAuditor\Formatters\JsonFormatter::class,
            $jsonFormatter
        );

        $markdownFormatter = AbstractFormatter::create('markdown', $sampleData);
        $this->assertInstanceOf(
            \CleaniqueCoders\DbSchemaAuditor\Formatters\MarkdownFormatter::class,
            $markdownFormatter
        );
    }

    /** @test */
    public function it_can_run_basic_audit_action()
    {
        // This test would need a proper database setup
        // For now, we'll just test that the action can be instantiated
        $action = new AuditDatabaseIntegrityAction;
        $this->assertInstanceOf(AuditDatabaseIntegrityAction::class, $action);
    }

    /** @test */
    public function json_formatter_produces_valid_json()
    {
        $sampleData = [
            'issues' => [
                'missing_indexes' => [
                    [
                        'table' => 'users',
                        'column' => 'email',
                        'severity' => 'high',
                        'recommendation' => 'Add index on users.email',
                    ],
                ],
            ],
            'stats' => [
                'total_tables' => 5,
                'issues_found' => 1,
                'database_type' => 'mysql',
            ],
            'audit_time' => now()->toISOString(),
        ];

        $formatter = AbstractFormatter::create('json', $sampleData);
        $output = $formatter->format();

        $this->assertJson($output);

        $decoded = json_decode($output, true);
        $this->assertArrayHasKey('audit_results', $decoded);
        $this->assertArrayHasKey('summary', $decoded);
        $this->assertArrayHasKey('metadata', $decoded);
    }

    /** @test */
    public function console_formatter_produces_readable_output()
    {
        $sampleData = [
            'issues' => [
                'missing_indexes' => [
                    [
                        'table' => 'users',
                        'column' => 'email',
                        'severity' => 'high',
                        'recommendation' => 'Add index on users.email',
                    ],
                ],
            ],
            'stats' => [
                'total_tables' => 5,
                'issues_found' => 1,
                'database_type' => 'mysql',
            ],
            'audit_time' => now()->toISOString(),
        ];

        $formatter = AbstractFormatter::create('console', $sampleData);
        $output = $formatter->format();

        $this->assertStringContainsString('DATABASE SCHEMA AUDIT', $output);
        $this->assertStringContainsString('Missing Indexes', $output);
        $this->assertStringContainsString('users.email', $output);
    }
}
