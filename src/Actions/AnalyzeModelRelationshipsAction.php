<?php

namespace CleaniqueCoders\DbSchemaAuditor\Actions;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use ReflectionClass;
use ReflectionMethod;

class AnalyzeModelRelationshipsAction
{
    use AsAction;

    public function handle(?string $modelsPath = null): array
    {
        $modelsPath = $modelsPath ?? app_path('Models');

        if (! File::exists($modelsPath)) {
            return [
                'models' => [],
                'issues' => [],
                'stats' => [
                    'total_models' => 0,
                    'models_analyzed' => 0,
                    'relationships_found' => 0,
                    'issues_found' => 0,
                ],
            ];
        }

        $modelFiles = File::allFiles($modelsPath);
        $modelAnalysis = [];
        $allIssues = [
            'missing_inverse_relationships' => [],
            'naming_inconsistencies' => [],
            'potential_missing_relationships' => [],
            'relationship_method_issues' => [],
        ];

        $stats = [
            'total_models' => count($modelFiles),
            'models_analyzed' => 0,
            'relationships_found' => 0,
            'issues_found' => 0,
        ];

        foreach ($modelFiles as $file) {
            $className = $this->getClassNameFromFile($file, $modelsPath);

            if (! $className || ! class_exists($className)) {
                continue;
            }

            try {
                $analysis = $this->analyzeModel($className);
                $modelAnalysis[$className] = $analysis;

                $stats['models_analyzed']++;
                $stats['relationships_found'] += count($analysis['relationships']);

            } catch (\Exception $e) {
                // Skip models that can't be analyzed
                continue;
            }
        }

        // Cross-reference relationships to find issues
        $this->findRelationshipIssues($modelAnalysis, $allIssues);

        $stats['issues_found'] = array_sum(array_map('count', $allIssues));

        return [
            'models' => $modelAnalysis,
            'issues' => $allIssues,
            'stats' => $stats,
            'analysis_time' => now()->toISOString(),
        ];
    }

    protected function getClassNameFromFile($file, string $modelsPath): ?string
    {
        $relativePath = $file->getRelativePathname();
        $className = 'App\\Models\\'.str_replace(['/', '.php'], ['\\', ''], $relativePath);

        return $className;
    }

    protected function analyzeModel(string $className): array
    {
        $reflection = new ReflectionClass($className);

        if ($reflection->isAbstract()) {
            throw new \Exception('Abstract class');
        }

        // Try to get table name
        try {
            $model = app($className);
            $tableName = $model->getTable();
        } catch (\Exception $e) {
            throw new \Exception('Could not instantiate model');
        }

        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        $relationships = [];

        foreach ($methods as $method) {
            if ($method->class !== $className) {
                continue;
            }

            if ($this->isRelationshipMethod($method)) {
                $relationship = $this->analyzeRelationshipMethod($method);
                if ($relationship) {
                    $relationships[] = $relationship;
                }
            }
        }

        return [
            'class' => $className,
            'table' => $tableName,
            'relationships' => $relationships,
            'file' => $reflection->getFileName(),
        ];
    }

    protected function isRelationshipMethod(ReflectionMethod $method): bool
    {
        // Skip magic methods and common model methods
        if (Str::startsWith($method->name, '__') ||
            in_array($method->name, [
                'boot', 'booted', 'booting', 'setAttribute', 'getAttribute',
                'toArray', 'toJson', 'getTable', 'getKeyName', 'getIncrementing',
            ])) {
            return false;
        }

        // Read method source to check for relationship calls
        try {
            $sourceFile = $method->getFileName();
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();

            $sourceLines = file($sourceFile);
            $methodSource = implode('', array_slice($sourceLines, $startLine - 1, $endLine - $startLine + 1));

            $relationshipTypes = [
                'belongsTo', 'hasOne', 'hasMany', 'belongsToMany',
                'hasManyThrough', 'hasOneThrough', 'morphTo', 'morphOne',
                'morphMany', 'morphToMany', 'morphedByMany',
            ];

            foreach ($relationshipTypes as $relType) {
                if (preg_match('/\$this->'.$relType.'\s*\(/i', $methodSource)) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            // If we can't read the source, assume it's not a relationship
        }

        return false;
    }

    protected function analyzeRelationshipMethod(ReflectionMethod $method): ?array
    {
        try {
            $sourceFile = $method->getFileName();
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();

            $sourceLines = file($sourceFile);
            $methodSource = implode('', array_slice($sourceLines, $startLine - 1, $endLine - $startLine + 1));

            $relationshipTypes = [
                'belongsTo', 'hasOne', 'hasMany', 'belongsToMany',
                'hasManyThrough', 'hasOneThrough', 'morphTo', 'morphOne',
                'morphMany', 'morphToMany', 'morphedByMany',
            ];

            foreach ($relationshipTypes as $relType) {
                if (preg_match('/\$this->'.$relType.'\s*\(/i', $methodSource, $matches)) {
                    // Extract the related model class
                    $relatedModel = null;
                    if (preg_match('/'.$relType.'\s*\(\s*([^:,\)]+)/', $methodSource, $modelMatches)) {
                        $relatedModel = trim($modelMatches[1], ' "\',');

                        // Clean up the model class name
                        $relatedModel = str_replace(['self::', 'static::'], '', $relatedModel);
                        if (! str_contains($relatedModel, '\\') && ! str_contains($relatedModel, '::')) {
                            $relatedModel = trim($relatedModel, ' "\',');
                        }
                    }

                    return [
                        'method' => $method->name,
                        'type' => $relType,
                        'related_model' => $relatedModel,
                        'line' => $startLine,
                        'source_snippet' => trim($methodSource),
                    ];
                }
            }
        } catch (\Exception $e) {
            // Return null if we can't analyze the method
        }

        return null;
    }

    protected function findRelationshipIssues(array $modelAnalysis, array &$issues): void
    {
        foreach ($modelAnalysis as $modelClass => $analysis) {
            foreach ($analysis['relationships'] as $relationship) {
                $this->checkForInverseRelationship($modelClass, $relationship, $modelAnalysis, $issues);
                $this->checkNamingConsistency($modelClass, $relationship, $issues);
                $this->checkRelationshipMethodIssues($modelClass, $relationship, $issues);
            }
        }
    }

    protected function checkForInverseRelationship(string $modelClass, array $relationship, array $modelAnalysis, array &$issues): void
    {
        $relatedModel = $relationship['related_model'];
        $relType = $relationship['type'];

        if (! $relatedModel || ! isset($modelAnalysis[$relatedModel])) {
            return;
        }

        // Define expected inverse relationships
        $expectedInverse = match ($relType) {
            'belongsTo' => ['hasOne', 'hasMany'],
            'hasOne' => ['belongsTo'],
            'hasMany' => ['belongsTo'],
            'belongsToMany' => ['belongsToMany'],
            default => [],
        };

        if (empty($expectedInverse)) {
            return;
        }

        // Check if the related model has an inverse relationship
        $relatedModelAnalysis = $modelAnalysis[$relatedModel];
        $hasInverse = false;

        foreach ($relatedModelAnalysis['relationships'] as $relatedRelationship) {
            if (in_array($relatedRelationship['type'], $expectedInverse) &&
                $relatedRelationship['related_model'] === $modelClass) {
                $hasInverse = true;
                break;
            }
        }

        if (! $hasInverse) {
            $issues['missing_inverse_relationships'][] = [
                'model' => $modelClass,
                'relationship' => $relationship['method'],
                'type' => $relType,
                'related_model' => $relatedModel,
                'expected_inverse' => $expectedInverse,
                'severity' => 'medium',
                'recommendation' => "Consider adding inverse relationship in {$relatedModel}",
            ];
        }
    }

    protected function checkNamingConsistency(string $modelClass, array $relationship, array &$issues): void
    {
        $methodName = $relationship['method'];
        $relType = $relationship['type'];

        // Check naming conventions
        $expectedNaming = match ($relType) {
            'belongsTo', 'hasOne', 'morphOne', 'morphTo' => 'singular',
            'hasMany', 'belongsToMany', 'morphMany', 'morphToMany' => 'plural',
            default => null,
        };

        if (! $expectedNaming) {
            return;
        }

        $isSingular = Str::singular($methodName) === $methodName;
        $isPlural = Str::plural($methodName) === $methodName;

        if (($expectedNaming === 'singular' && ! $isSingular) ||
            ($expectedNaming === 'plural' && ! $isPlural)) {

            $issues['naming_inconsistencies'][] = [
                'model' => $modelClass,
                'relationship' => $methodName,
                'type' => $relType,
                'issue' => "Method name should be {$expectedNaming}",
                'severity' => 'low',
                'recommendation' => $expectedNaming === 'singular'
                    ? 'Rename to '.Str::singular($methodName)
                    : 'Rename to '.Str::plural($methodName),
            ];
        }
    }

    protected function checkRelationshipMethodIssues(string $modelClass, array $relationship, array &$issues): void
    {
        // Check for common issues in relationship definitions
        $source = $relationship['source_snippet'] ?? '';

        // Check for missing foreign key specification in belongsTo
        if ($relationship['type'] === 'belongsTo' &&
            ! preg_match('/,\s*[\'"][^\'",]+_id[\'"]/', $source)) {

            $issues['relationship_method_issues'][] = [
                'model' => $modelClass,
                'relationship' => $relationship['method'],
                'type' => $relationship['type'],
                'issue' => 'missing_foreign_key_specification',
                'severity' => 'low',
                'recommendation' => 'Consider explicitly specifying foreign key for clarity',
            ];
        }

        // Check for potential missing pivot table in belongsToMany
        if ($relationship['type'] === 'belongsToMany' &&
            ! preg_match('/->withPivot|->withTimestamps/', $source)) {

            $issues['relationship_method_issues'][] = [
                'model' => $modelClass,
                'relationship' => $relationship['method'],
                'type' => $relationship['type'],
                'issue' => 'missing_pivot_configuration',
                'severity' => 'low',
                'recommendation' => 'Consider adding pivot table configuration if needed',
            ];
        }
    }
}
