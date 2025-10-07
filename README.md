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

```php
# Audit Database Design - by default it will display summary of audit in console
php artisan db:audit

# Output as JSON - default path to /database/audit/
# Available format - console(default), json, markdown, csv, database
php artisan db:audit --format=json

# Output as JSON to specified directory
php artisan db:audit --format=json --path=/storage/audit

# Output as Markdown
php artisan db:audit --format=md
```

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
