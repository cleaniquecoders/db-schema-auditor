<?php

namespace CleaniqueCoders\DbSchemaAuditor\Commands;

use Illuminate\Console\Command;

class DbSchemaAuditorCommand extends Command
{
    public $signature = 'db-schema-auditor';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
