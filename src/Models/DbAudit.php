<?php

namespace CleaniqueCoders\DbSchemaAuditor\Models;

use CleaniqueCoders\Traitify\Concerns\InteractsWithUuid;
use Illuminate\Database\Eloquent\Model;

class DbAudit extends Model
{
    use InteractsWithUuid;

    protected $casts = [
        'result' => 'array',
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
