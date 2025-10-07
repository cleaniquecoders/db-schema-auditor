# Audit Database Schema Design

[![Latest Version on Packagist](https://img.shields.io/packagist/v/cleaniquecoders/db-schema-auditor.svg?style=flat-square)](https://packagist.org/packages/cleaniquecoders/db-schema-auditor)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/cleaniquecoders/db-schema-auditor/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/cleaniquecoders/db-schema-auditor/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/cleaniquecoders/db-schema-auditor/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/cleaniquecoders/db-schema-auditor/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/cleaniquecoders/db-schema-auditor.svg?style=flat-square)](https://packagist.org/packages/cleaniquecoders/db-schema-auditor)

Audit Database Schema Design.

## Installation

You can install the package via composer:

```bash
composer require cleaniquecoders/db-schema-auditor --dev
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="db-schema-auditor-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="db-schema-auditor-config"
```

## Usage

```bash
# Basic audit - displays summary in console
php artisan db:audit

# Audit with model relationship analysis
php artisan db:audit --models

# Output as JSON to default path (/database/audit/)
php artisan db:audit --format=json

# Output as JSON to specified directory
php artisan db:audit --format=json --path=/storage/audit

# Output as Markdown
php artisan db:audit --format=markdown

# Output as CSV
php artisan db:audit --format=csv

# Output as HTML report
php artisan db:audit --format=html

# Generate fix migrations automatically
php artisan db:audit --generate-fixes

# Save results to database for tracking
php artisan db:audit --save-database

# Audit specific database connection
php artisan db:audit --connection=secondary

# Full audit with all options
php artisan db:audit --models --generate-fixes --save-database --format=markdown
```

### Supported Database Types

This package supports all major database types:

- **MySQL** / **MariaDB** - Full support for indexes, foreign keys, and constraints
- **PostgreSQL** - Complete support including partial indexes and advanced features
- **Microsoft SQL Server** - Full support for SQL Server specific features
- **Oracle** - Support for Oracle-specific indexes and constraints
- **SQLite** - Basic support for SQLite databases

### Output Formats

- **console** (default) - Colored terminal output with recommendations
- **json** - Machine-readable JSON format for integration
- **markdown** - GitHub-flavored markdown for documentation
- **csv** - Spreadsheet-compatible format for analysis
- **html** - Styled HTML report for sharing

### What Gets Audited

**Database Structure:**

- Missing indexes on foreign key columns
- Missing indexes on commonly queried columns
- Missing unique constraints
- Missing foreign key constraints
- Orphaned records in relationships
- Suspicious column patterns

**Model Relationships (with --models flag):**

- Missing inverse relationships
- Relationship naming inconsistencies
- Missing model relationships for database foreign keys
- Relationship method issues

### Generated Outputs

**Migration Files (with --generate-fixes):**

- Index creation migrations
- Unique constraint migrations
- Foreign key suggestion migrations (commented for review)
- Orphaned record cleanup scripts

**Database Storage (with --save-database):**

- Complete audit history
- Issue tracking and resolution status
- Model analysis results
- Performance trending over time

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Nasrul Hazim Bin Mohamad](https://github.com/nasrulhazim)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
