<?php

namespace CleaniqueCoders\DbSchemaAuditor\Models;

use CleaniqueCoders\Traitify\Concerns\InteractsWithUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $uuid
 * @property int $audit_id
 * @property string $model_class
 * @property string $table_name
 * @property int $relationships_found
 * @property array|null $relationships_data
 * @property int $issues_found
 * @property array|null $issues_data
 * @property string|null $file_path
 */
class DbAuditModelAnalysis extends Model
{
    use HasFactory, InteractsWithUuid;

    protected $fillable = [
        'uuid',
        'audit_id',
        'model_class',
        'table_name',
        'relationships_found',
        'relationships_data',
        'issues_found',
        'issues_data',
        'file_path',
    ];

    protected $casts = [
        'relationships_data' => 'array',
        'issues_data' => 'array',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(DbAudit::class, 'audit_id');
    }

    public function scopeByModel($query, string $modelClass)
    {
        return $query->where('model_class', $modelClass);
    }

    public function scopeByTable($query, string $table)
    {
        return $query->where('table_name', $table);
    }

    public function scopeWithIssues($query)
    {
        return $query->where('issues_found', '>', 0);
    }

    public function scopeWithoutIssues($query)
    {
        return $query->where('issues_found', 0);
    }

    public function getModelNameAttribute(): string
    {
        return class_basename($this->model_class);
    }

    public function getRelationshipTypesAttribute(): array
    {
        if (empty($this->relationships_data)) {
            return [];
        }

        return collect($this->relationships_data)
            ->pluck('type')
            ->unique()
            ->values()
            ->toArray();
    }

    public function getIssueTypesAttribute(): array
    {
        if (empty($this->issues_data)) {
            return [];
        }

        return collect($this->issues_data)
            ->keys()
            ->toArray();
    }
}
