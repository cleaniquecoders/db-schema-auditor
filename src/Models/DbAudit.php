<?php

namespace CleaniqueCoders\DbSchemaAuditor\Models;

use CleaniqueCoders\Traitify\Concerns\InteractsWithUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DbAudit extends Model
{
    use HasFactory, InteractsWithUuid;

    protected $fillable = [
        'uuid',
        'connection_name',
        'database_type',
        'total_tables',
        'tables_scanned',
        'total_issues',
        'audit_config',
        'execution_time',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'audit_config' => 'array',
        'execution_time' => 'float',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function issues(): HasMany
    {
        return $this->hasMany(DbAuditIssue::class, 'audit_id');
    }

    public function modelAnalysis(): HasMany
    {
        return $this->hasMany(DbAuditModelAnalysis::class, 'audit_id');
    }

    public function getIssuesByType(): array
    {
        return $this->issues()
            ->get()
            ->groupBy('issue_type')
            ->map(fn ($issues) => $issues->toArray())
            ->toArray();
    }

    public function getIssuesBySeverity(): array
    {
        return $this->issues()
            ->get()
            ->groupBy('severity')
            ->map(fn ($issues) => $issues->count())
            ->toArray();
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByConnection($query, string $connection)
    {
        return $query->where('connection_name', $connection);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
