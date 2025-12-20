<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Action Schema Model
 *
 * Stores OpenAPI schemas for workflow actions, including:
 * - Internal PHP actions/services
 * - External API endpoints (Stripe, Twilio, etc.)
 * - Webhooks
 * - Custom integrations
 *
 * @property int $id
 * @property string $action_class
 * @property string $action_type
 * @property string|null $method
 * @property array $schema OpenAPI schema
 * @property string $name
 * @property string|null $description
 * @property string $category
 * @property array|null $tags
 * @property bool $is_active
 * @property bool $is_external
 * @property string $source
 * @property int $version
 * @property array|null $config
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class ActionSchema extends Model
{
    use SoftDeletes;

    protected $table = 'action_schemas';

    protected $fillable = [
        'action_class',
        'action_type',
        'method',
        'schema',
        'name',
        'description',
        'category',
        'tags',
        'is_active',
        'is_external',
        'source',
        'version',
        'config',
    ];

    protected $casts = [
        'schema' => 'array',
        'tags' => 'array',
        'config' => 'array',
        'is_active' => 'boolean',
        'is_external' => 'boolean',
        'version' => 'integer',
    ];

    /**
     * Scope to only active schemas.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to internal actions only.
     */
    public function scopeInternal($query)
    {
        return $query->where('is_external', false);
    }

    /**
     * Scope to external actions only.
     */
    public function scopeExternal($query)
    {
        return $query->where('is_external', true);
    }

    /**
     * Scope to specific category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to specific action type.
     */
    public function scopeType($query, string $type)
    {
        return $query->where('action_type', $type);
    }

    /**
     * Get full action identifier.
     */
    public function getFullIdentifierAttribute(): string
    {
        return $this->method
            ? "{$this->action_class}::{$this->method}"
            : $this->action_class;
    }

    /**
     * Get parameters from schema.
     */
    public function getParametersAttribute(): array
    {
        return $this->schema['parameters'] ?? [];
    }

    /**
     * Get response schema.
     */
    public function getResponseSchemaAttribute(): array
    {
        return $this->schema['responses']['200']['content']['application/json']['schema'] ?? [];
    }

    /**
     * Check if this is a code-based schema.
     */
    public function isCodeBased(): bool
    {
        return $this->source === 'code';
    }

    /**
     * Check if this schema can be edited.
     */
    public function isEditable(): bool
    {
        return in_array($this->source, ['manual', 'imported']);
    }

    /**
     * Create a new version of this schema.
     */
    public function createVersion(array $newSchema): self
    {
        return static::create([
            'action_class' => $this->action_class,
            'action_type' => $this->action_type,
            'method' => $this->method,
            'schema' => $newSchema,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'tags' => $this->tags,
            'is_active' => true,
            'is_external' => $this->is_external,
            'source' => $this->source,
            'version' => $this->version + 1,
            'config' => $this->config,
        ]);
    }
}
