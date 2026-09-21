<?php

namespace App\Models\Concerns;

use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Model;

/**
 * Declara la regla de normalización de cada atributo string al guardar.
 *
 * Uso en el modelo:
 *
 *     use NormalizesAttributes;
 *
 *     protected array $normalizedAttributes = [
 *         'email'     => 'email',       // minúsculas, sin espacios
 *         'full_name' => 'personName',  // respeta capitalización y tildes
 *         'address'   => 'freeText',    // intacto (trim)
 *         'reference' => 'code',        // mayúsculas
 *         'name'      => 'catalogName', // catálogo controlado en mayúsculas
 *     ];
 *
 * Reglas disponibles: los métodos públicos homónimos de TextNormalizer.
 * Un modelo que use este trait queda fuera del hook global de mayúsculas
 * (ModelUppercaseNormalizer): solo se transforman los atributos declarados.
 */
trait NormalizesAttributes
{
    public static function bootNormalizesAttributes(): void
    {
        static::saving(static function (Model $model): void {
            foreach (($model->normalizedAttributes ?? []) as $attribute => $rule) {
                $value = $model->getAttributes()[$attribute] ?? null;

                if (!is_string($value) || !method_exists(TextNormalizer::class, $rule)) {
                    continue;
                }

                $model->setAttribute($attribute, TextNormalizer::{$rule}($value));
            }
        });
    }
}
