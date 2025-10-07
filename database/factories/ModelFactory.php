<?php

namespace VendorName\Skeleton\Database\Factories;

use CleaniqueCoders\DbSchemaAuditor\Models\DbAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ModelFactory extends Factory
{
    protected $model = DbAudit::class;

    public function definition()
    {
        return [
            'remarks' => fake()->paragraph(),
        ];
    }
}
