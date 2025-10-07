<?php

namespace CleaniqueCoders\DbSchemaAuditor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \CleaniqueCoders\DbSchemaAuditor\DbSchemaAuditor
 */
class DbSchemaAuditor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \CleaniqueCoders\DbSchemaAuditor\DbSchemaAuditor::class;
    }
}
