<?php

namespace Modules\Catalog\Traits;

use Closure;
use Illuminate\Support\Str;

trait HasSlug
{
    protected static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name ?? $model->title ?? '');
            }
        });
    }

    /**
     * Validate that a model name generates a non-empty, unique slug (including soft-deleted rows).
     */
    public static function slugValidationRule(string $modelClass, ?int $ignoreId = null, string $label = 'item'): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($modelClass, $ignoreId, $label): void {
            $slug = Str::slug((string) $value);

            if ($slug === '') {
                $fail("The {$label} name must contain letters or numbers.");
                return;
            }

            $query = method_exists($modelClass, 'withTrashed')
                ? $modelClass::withTrashed()
                : $modelClass::query();

            $exists = $query
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();

            if ($exists) {
                $fail("A {$label} with this name already exists.");
            }
        };
    }
}
