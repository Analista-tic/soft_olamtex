<?php

namespace App\Support;

use App\Models\Concerns\NormalizesAttributes;
use Illuminate\Database\Eloquent\Model;

class ModelUppercaseNormalizer
{
    private const DEFAULT_EXCLUDED_ATTRIBUTES = [
        'password',
        'remember_token',
        'token',
        'api_token',
        'created_at',
        'updated_at',
        'deleted_at',
        'email_verified_at',
    ];

    private const EXCLUDED_KEY_PARTS = [
        'password',
        'token',
        'hash',
        'uuid',
        'guid',
        'email',
        'url',
        'path',
        'file',
        'image',
        'img',
        'mime',
        'extension',
        'json',
    ];

    private const EXCLUDED_CAST_TYPES = [
        'array',
        'json',
        'object',
        'collection',
        'date',
        'datetime',
        'immutable_date',
        'immutable_datetime',
        'timestamp',
        'hashed',
        'encrypted',
        'encrypted:array',
        'encrypted:collection',
        'encrypted:json',
        'encrypted:object',
    ];

    public static function apply(Model $model): void
    {
        // Los modelos que declaran sus reglas (trait NormalizesAttributes) no usan el hook global.
        if (in_array(NormalizesAttributes::class, class_uses_recursive($model), true)) {
            return;
        }

        foreach ($model->getAttributes() as $key => $value) {
            if (!is_string($value) || !self::shouldNormalize($model, $key)) {
                continue;
            }

            $model->setAttribute($key, mb_strtoupper($value, 'UTF-8'));
        }
    }

    private static function shouldNormalize(Model $model, string $key): bool
    {
        $normalizedKey = strtolower($key);

        if (in_array($normalizedKey, self::DEFAULT_EXCLUDED_ATTRIBUTES, true)) {
            return false;
        }

        foreach (self::EXCLUDED_KEY_PARTS as $part) {
            if (str_contains($normalizedKey, $part)) {
                return false;
            }
        }

        $castType = strtolower((string) ($model->getCasts()[$key] ?? ''));
        if ($castType !== '' && in_array($castType, self::EXCLUDED_CAST_TYPES, true)) {
            return false;
        }

        return true;
    }
}