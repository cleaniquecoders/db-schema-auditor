# Changelog

All notable changes to `db-schema-auditor` will be documented in this file.

## 1.1.0 - 2026-03-31

### What's Changed

#### Added

- Laravel 13 support (illuminate constraints include `^13.0`)
- PHPUnit 12 compatibility
- Pest 4 support

#### Changed

- Updated `phpunit.xml.dist` for PHPUnit 12
- Standardized CI workflow (Laravel 12 + PHP 8.4/8.3)
- Updated dev dependencies (larastan, phpstan plugins, collision)

**Full Changelog**: https://github.com/cleaniquecoders/db-schema-auditor/compare/1.0.0...1.1.0

## First Release - 2025-10-07

### 📋 DB Schema Auditor v1.0.0 - Release Summary

**A comprehensive Laravel package for database schema auditing and optimization.**

#### 🎯 Key Features

- **Multi-database support** (MySQL, PostgreSQL, SQL Server, Oracle, SQLite)
- **Schema analysis** - detects missing indexes, constraints, and orphaned records
- **Model relationship validation** - analyzes Eloquent relationships
- **Auto-fix generation** - creates migration files for detected issues
- **Multiple output formats** - console, JSON, Markdown, CSV, HTML

#### 🚀 Quick Usage

```bash
# Basic audit
php artisan db:audit

# Full audit with fixes
php artisan db:audit --models --generate-fixes --format=markdown


```
#### 💡 Benefits

- **Performance optimization** through missing index detection
- **Data integrity** via constraint validation
- **Automated reporting** with multiple export formats
- **CI/CD integration** ready

**Installation:** `composer require cleaniquecoders/db-schema-auditor --dev`

Perfect for maintaining healthy database schemas and improving application performance! 🚀
