<?php

namespace App\Models\Production;

use App\Models\Concerns\HasAuditLabel;
use App\Models\Settings\AuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

/**
 * Catálogo maestro de operaciones de confección.
 *
 * Este modelo define la operación base (plantilla) que luego se asigna a
 * listados operacionales por sábana. Sus valores son referencia para cálculos
 * y para autocompletar información en ProductOperationList.
 *
 * Campos principales:
 * - codigo: código interno de la operación.
 * - action: acción principal (ej. CERRAR, UNIR, FILETEAR).
 * - garment_part: parte de prenda donde aplica la operación.
 * - anexo: detalle complementario opcional.
 * - section_id: sección del proceso (PREPARACION, ENSAMBLE, TERMINACION).
 * - measure: medida en centímetros usada en la descripción/cálculo.
 * - quantity: cantidad base de repeticiones de la operación.
 * - description: descripción normalizada autogenerada.
 * - sam_base: tiempo estándar base de la operación (minutos).
 * - factor_operario: eficiencia operario base (porcentaje).
 * - product_sheet_id_reference: sábana de referencia opcional.
 * - machine_id_reference: máquina de referencia de la operación.
 * - is_active: estado lógico de activación.
 */
class Operation extends Model
{
    use HasFactory, SoftDeletes, HasAuditLabel;

    protected $table = 'operations';

    protected $fillable = [
        'codigo',
        'action',
        'garment_part',
        'anexo',
        'section_id',
        'measure',
        'quantity',
        'description',
        'sam_base',
        'factor_operario',
        'product_sheet_id_reference',
        'machine_id_reference',
        'is_active',
    ];

    public array $auditIgnore = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'section_id' => 'integer',
        'measure' => 'float',
        'quantity' => 'integer',
        'sam_base' => 'float',
        'factor_operario' => 'float',
        'product_sheet_id_reference' => 'integer',
        'machine_id_reference' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = ['name', 'generated_description'];

    protected $with = [];

    protected static function booted(): void
    {
        static::saving(function (self $operation) {
            // Normalizar valores básicos  
            $samBase = is_numeric((string) $operation->sam_base) ? (float) $operation->sam_base : 0.0;
            $operation->sam_base = max(0, $samBase); // No negativo
            
            $factorOperario = is_numeric((string) $operation->factor_operario) ? (float) $operation->factor_operario : 100.0;
            $operation->factor_operario = max(0.01, $factorOperario); // Validar es positivo
            
            // Generar descripción
            $operation->description = $operation->composeDescription();

            $currentSignature = $operation->buildUniquenessSignature();
            $originalSignature = $operation->exists
                ? $operation->buildUniquenessSignatureFromAttributes($operation->getOriginal())
                : null;

            if (! $operation->exists || $currentSignature !== $originalSignature) {
                $duplicate = self::query()
                    ->whereNull('deleted_at')
                    ->where('id', '!=', $operation->id)
                    ->where('action', strtoupper(trim((string) $operation->action)))
                    ->where('garment_part', strtoupper(trim((string) $operation->garment_part)))
                    ->get()
                    ->first(fn (self $candidate) => $candidate->buildUniquenessSignature() === $currentSignature);

                if ($duplicate) {
                    throw new \InvalidArgumentException('Ya existe una operación con la misma firma de unicidad (sección, acción, parte, medida, cantidad y SAM base).');
                }
            }
        });
    }

    private function buildUniquenessSignature(): string
    {
        return $this->buildUniquenessSignatureFromAttributes([
            'section_id' => $this->section_id,
            'action' => $this->action,
            'garment_part' => $this->garment_part,
            'measure' => $this->measure,
            'quantity' => $this->quantity,
            'sam_base' => $this->sam_base,
        ]);
    }

    private function buildUniquenessSignatureFromAttributes(array $attributes): string
    {
        $sectionId = $this->normalizeInt($attributes['section_id'] ?? null);
        $measure = $this->normalizeFloat($attributes['measure'] ?? 0, 4) ?? '0.0000';
        $samBase = $this->normalizeFloat($attributes['sam_base'] ?? 0, 4) ?? '0.0000';
        $quantity = $this->normalizeInt($attributes['quantity'] ?? 1) ?? 1;

        return implode('|', [
            (string) ($sectionId ?? ''),
            $this->normalizeText($attributes['action'] ?? null),
            $this->normalizeText($attributes['garment_part'] ?? null),
            $measure,
            (string) $quantity,
            $samBase,
        ]);
    }

    private function normalizeText(?string $value): string
    {
        return mb_strtoupper(trim((string) ($value ?? '')));
    }

    private function normalizeInt(int|string|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function normalizeFloat(float|int|string|null $value, int $precision = 4): ?string
    {
        if ($value === null || $value === '' || !is_numeric((string) $value)) {
            return null;
        }

        return number_format((float) $value, $precision, '.', '');
    }

    /**
     * Medida para la descripción ("10", "10.5"). Solo se quitan ceros de los decimales (antes "10" salía "1");
     * 0 o vacío significa "no aplica" y no aparece en la descripción.
     */
    public static function formatMeasure(float|int|string|null $measure): ?string
    {
        if ($measure === null || $measure === '' || ! is_numeric($measure) || (float) $measure <= 0) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $measure, 4, '.', ''), '0'), '.');
    }

    private function composeDescription(): string
    {
        $parts = [
            strtoupper(trim((string) $this->action)),
            strtoupper(trim((string) $this->garment_part)),
        ];

        $anexo = trim((string) ($this->anexo ?? ''));
        if ($anexo !== '') {
            $parts[] = strtoupper($anexo);
        }

        $measureLabel = self::formatMeasure($this->measure);
        if ($measureLabel !== null) {
            $parts[] = $measureLabel . ' CM';
        }

        if ($this->quantity !== null && is_numeric((string) $this->quantity)) {
            $parts[] = 'X' . (string) ((int) $this->quantity);
        }

        return trim(implode(' ', $parts));
    }

    // Relaciones
    public function section()
    {
        return $this->belongsTo(OperationSection::class, 'section_id');
    }

    public function productSheetReference()
    {
        return $this->belongsTo(ProductSheet::class, 'product_sheet_id_reference');
    }

    public function machineReference()
    {
        return $this->belongsTo(Machine::class, 'machine_id_reference');
    }

    public function productOperationLists()
    {
        return $this->hasMany(ProductOperationList::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'auditable_id')
            ->where('auditable_type', self::class);
    }

    // Accessors
    public function getNameAttribute(): string
    {
        $base = trim("{$this->action} {$this->garment_part}");
        return $this->anexo ? "{$base} · {$this->anexo}" : $base;
    }

    public function getNormalizedNameAttribute(): string
    {
        $base = strtoupper(trim("{$this->action} {$this->garment_part}"));
        return $this->anexo ? "{$base} · " . strtoupper(trim($this->anexo)) : $base;
    }

    /**
     * Descripción auto-generada a partir de los campos de la operación.
     */
    public function getGeneratedDescriptionAttribute(): string
    {
        return $this->composeDescription();
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeByGarmentPart(Builder $query, string $garmentPart): Builder
    {
        return $query->where('garment_part', $garmentPart);
    }

    public function scopeByNormalizedName(Builder $query, string $action, string $garmentPart): Builder
    {
        return $query
            ->where('action', strtoupper(trim($action)))
            ->where('garment_part', strtoupper(trim($garmentPart)));
    }
}