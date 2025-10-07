<?php

namespace CleaniqueCoders\DbSchemaAuditor;

use CleaniqueCoders\DbSchemaAuditor\Commands\DbSchemaAuditorCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DbSchemaAuditorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('db-schema-auditor')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_db_schema_auditor_table')
            ->hasCommand(DbSchemaAuditorCommand::class);
    }
}
