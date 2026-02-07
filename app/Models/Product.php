<?php

namespace Modules\Billing\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, Sluggable, SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope('ordered', function ($query) {
            $query->orderBy('display_order', 'asc');
        });
    }

    protected $fillable = [
        'sku',
        'slug',
        'name',
        'description',
        'display_order',
        'is_visible',
        'is_highlighted',
        'features',
        'metadata',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
            'is_visible' => 'boolean',
            'is_highlighted' => 'boolean',
            'display_order' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Return the sluggable configuration array for this model.
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
            'sku' => [
                'source' => 'name',
                'separator' => '_',
            ],
        ];
    }

    /**
     * Scope a query to only include active products.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope a query to only include visible products.
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('is_visible', true);
    }

    /**
     * Scope a query to only include displayable products with active prices.
     */
    #[Scope]
    protected function displayable(Builder $query): void
    {
        $query->active()
            ->visible()
            ->with(['prices' => fn (HasMany $query) => $query->where('is_active', true)]);
    }

    /**
     * @return HasMany<Price, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }
}
