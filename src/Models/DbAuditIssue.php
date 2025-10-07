<?php

namespace CleaniqueCoders\DbSchemaAuditor\Models;

use CleaniqueCoders\Traitify\Concerns\InteractsWithUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $uuid
 * @property int $audit_id
 * @property string $issue_type
 * @property string $table_name
 * @property string|null $column_name
 * @property string $severity
 * @property string|null $recommendation
 * @property array|null $details
 * @property int|null $count
 * @property string|null $referenced_table
 * @property string|null $referenced_column
 * @property bool $fix_generated
 * @property bool $fix_applied
 */
class DbAuditIssue extends Model
{
    use HasFactory, InteractsWithUuid;

    protected $fillable = [
        'uuid',
        'audit_id',
        'issue_type',
        'table_name',
        'column_name',
        'severity',
        'recommendation',
        'details',
        'count',
        'referenced_table',
        'referenced_column',
        'fix_generated',
        'fix_applied',
    ];

    protected $casts = [
        'details' => 'array',
        'fix_generated' => 'boolean',
        'fix_applied' => 'boolean',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(DbAudit::class, 'audit_id');
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('issue_type', $type);
    }

    public function scopeByTable($query, string $table)
    {
        return $query->where('table_name', $table);
    }

    public function scopeUnfixed($query)
    {
        return $query->where('fix_applied', false);
    }

    public function scopeHigh($query)
    {
        return $query->where('severity', 'high');
    }

    public function scopeMedium($query)
    {
        return $query->where('severity', 'medium');
    }

    public function scopeLow($query)
    {
        return $query->where('severity', 'low');
    }

    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            'high' => 'red',
            'medium' => 'yellow',
            'low' => 'blue',
            default => 'gray',
        };
    }

    public function getSeverityIconAttribute(): string
    {
        return match ($this->severity) {
            'high' => '🔴',
            'medium' => '🟡',
            'low' => '🔵',
            default => '⚪',
        };
    }

    public function getTypeDisplayNameAttribute(): string
    {
        return match ($this->issue_type) {
            'missing_indexes' => 'Missing Index',
            'missing_unique_constraints' => 'Missing Unique Constraint',
            'missing_foreign_keys' => 'Missing Foreign Key',
            'orphaned_records' => 'Orphaned Records',
            'suspicious_columns' => 'Suspicious Column',
            'missing_inverse_relationships' => 'Missing Inverse Relationship',
            'naming_inconsistencies' => 'Naming Inconsistency',
            'potential_missing_relationships' => 'Potential Missing Relationship',
            'relationship_method_issues' => 'Relationship Method Issue',
            default => ucwords(str_replace('_', ' ', $this->issue_type)),
        };
    }
}
