<?php

namespace App\Models\Production;

use App\Models\Concerns\HasAuditLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

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
        });
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

        if ($this->measure !== null && is_numeric((string) $this->measure)) {
            $parts[] = rtrim(rtrim((string) $this->measure, '0'), '.') . ' CM';
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
        return $this->hasMany(OperationAuditLog::class);
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