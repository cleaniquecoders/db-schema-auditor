<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Schema Auditor Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the Database Schema Auditor
    | package. You can customize audit rules, database-specific settings,
    | and output preferences here.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    |
    | The default database connection to audit. If null, it will use the
    | application's default database connection.
    |
    */

    'default_connection' => null,

    /*
    |--------------------------------------------------------------------------
    | Audit Rules
    |--------------------------------------------------------------------------
    |
    | Configure which audit rules should be applied during the schema audit.
    | You can enable or disable specific audit checks here.
    |
    */

    'audit_rules' => [
        'missing_indexes' => true,
        'missing_unique_constraints' => true,
        'missing_foreign_keys' => true,
        'orphaned_records' => true,
        'suspicious_columns' => true,
        'model_relationships' => true,
        'naming_conventions' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Indexable Columns
    |--------------------------------------------------------------------------
    |
    | List of column names that should typically have indexes for performance.
    | The auditor will flag these columns if they don't have indexes.
    |
    */

    'common_indexable_columns' => [
        'email',
        'uuid',
        'status',
        'type',
        'code',
        'slug',
        'name',
        'active',
        'enabled',
        'published',
        'deleted_at',
        'created_at',
        'updated_at',
        'sort_order',
        'position',
        'category_id',
        'user_id',
        'parent_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Unique Constraint Columns
    |--------------------------------------------------------------------------
    |
    | List of column names that should have unique constraints to prevent
    | duplicate data.
    |
    */

    'unique_required_columns' => [
        'email',
        'uuid',
        'slug',
        'code',
        'username',
        'api_token',
        'remember_token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Foreign Key Exclusions
    |--------------------------------------------------------------------------
    |
    | Columns ending with '_id' that should NOT be considered for foreign key
    | constraints. These are typically system columns or special cases.
    |
    */

    'foreign_key_exclusions' => [
        'created_by',
        'updated_by',
        'deleted_by',
        'approved_by',
        'rejected_by',
        'processed_by',
        'external_id',
        'legacy_id',
        'old_id',
        'temp_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Exclusions
    |--------------------------------------------------------------------------
    |
    | Tables that should be excluded from the audit. These are typically
    | system tables, temporary tables, or tables that don't follow standard
    | conventions.
    |
    */

    'excluded_tables' => [
        'db_audit%', // Wildcard for DB Audit Tables
        'migrations',
        'failed_jobs',
        'password_resets',
        'password_reset_tokens',
        'personal_access_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'telescope_%', // Wildcard for Telescope tables
    ],

    /*
    |--------------------------------------------------------------------------
    | Severity Thresholds
    |--------------------------------------------------------------------------
    |
    | Configure thresholds for different severity levels based on the impact
    | and importance of the issues found.
    |
    */

    'severity_thresholds' => [
        'orphaned_records' => [
            'high' => 100,   // More than 100 orphaned records
            'medium' => 10,  // 10-100 orphaned records
            'low' => 1,      // 1-9 orphaned records
        ],
        'missing_indexes' => [
            'foreign_key' => 'high',    // Missing index on foreign key
            'common_field' => 'medium', // Missing index on common fields
            'custom' => 'low',          // Missing index on custom fields
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Database-Specific Settings
    |--------------------------------------------------------------------------
    |
    | Database-specific configuration for different database types.
    | These settings override general settings for specific databases.
    |
    */

    'database_specific' => [
        'mysql' => [
            'check_fulltext_indexes' => true,
            'check_spatial_indexes' => false,
            'max_index_length' => 767, // For older MySQL versions
        ],
        'postgresql' => [
            'check_partial_indexes' => true,
            'check_gin_indexes' => true,
            'check_gist_indexes' => false,
        ],
        'sqlite' => [
            'check_without_rowid' => false,
            'check_virtual_tables' => false,
        ],
        'sqlserver' => [
            'check_clustered_indexes' => true,
            'check_columnstore_indexes' => false,
        ],
        'oracle' => [
            'check_bitmap_indexes' => false,
            'check_function_based_indexes' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Analysis Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for analyzing Eloquent model relationships and their
    | consistency with the database schema.
    |
    */

    'model_analysis' => [
        'enabled' => true,
        'models_path' => 'app/Models',
        'check_inverse_relationships' => true,
        'check_naming_conventions' => true,
        'check_pivot_tables' => true,
        'excluded_models' => [
            // Models to exclude from relationship analysis
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for different output formats.
    |
    */

    'output' => [
        'default_format' => 'console',
        'default_path' => 'database/audit',

        'formats' => [
            'console' => [
                'include_stats' => true,
                'include_recommendations' => true,
                'group_by_severity' => true,
                'show_sql_examples' => false,
            ],
            'json' => [
                'pretty_print' => true,
                'include_metadata' => true,
                'compact' => false,
            ],
            'markdown' => [
                'include_stats' => true,
                'include_recommendations' => true,
                'include_toc' => true,
                'github_flavored' => true,
            ],
            'csv' => [
                'include_headers' => true,
                'delimiter' => ',',
                'enclosure' => '"',
            ],
            'html' => [
                'include_stats' => true,
                'include_recommendations' => true,
                'responsive' => true,
                'dark_mode' => false,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Settings to control the performance impact of the audit process.
    |
    */

    'performance' => [
        'max_tables_per_batch' => 50,
        'max_orphaned_record_checks' => 1000,
        'timeout_seconds' => 300,
        'memory_limit' => '512M',
        'enable_query_cache' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configure notifications for audit results. You can set up email
    | notifications, Slack notifications, etc.
    |
    */

    'notifications' => [
        'enabled' => false,
        'channels' => [
            // 'mail' => [
            //     'to' => 'admin@example.com',
            //     'subject' => 'Database Audit Report',
            // ],
            // 'slack' => [
            //     'webhook_url' => env('SLACK_AUDIT_WEBHOOK'),
            //     'channel' => '#database-audits',
            // ],
        ],
        'threshold' => 'high', // Only notify for high-severity issues
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for storing audit results in the database.
    |
    */

    'storage' => [
        'enabled' => false,
        'table' => 'db_audits',
        'connection' => null, // Use default connection
        'retention_days' => 90, // Keep audit results for 90 days
        'cleanup_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Fix Generation Settings
    |--------------------------------------------------------------------------
    |
    | Settings for generating database fix migrations and scripts.
    |
    */

    'fix_generation' => [
        'enabled' => true,
        'migration_path' => 'database/migrations',
        'migration_prefix' => 'db_audit_fix',
        'create_backup_script' => true,
        'comment_foreign_keys' => true, // Comment out FK suggestions for review
        'group_by_table' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Rules
    |--------------------------------------------------------------------------
    |
    | Define custom audit rules specific to your application. These rules
    | will be executed in addition to the built-in rules.
    |
    */

    'custom_rules' => [
        // 'custom_rule_name' => CustomRuleClass::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for the audit process.
    |
    */

    'logging' => [
        'enabled' => true,
        'channel' => 'daily',
        'level' => 'info',
        'log_queries' => false, // Log SQL queries (for debugging)
    ],

];
