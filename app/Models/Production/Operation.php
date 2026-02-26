<?php

namespace App\Models\Production;

use App\Models\Concerns\HasAuditLabel;
use App\Models\Settings\User;
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
        'section_id',
        'location',
        'measure',
        'quantity',
        'description',
        'sam_base',
        'product_sheet_id_reference',
        'machine_id_reference',
        'is_active',
    ];

    public array $auditIgnore = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'measure' => 'float',
        'quantity' => 'integer',
        'sam_base' => 'float',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = ['name', 'generated_description'];

    protected $with = [];

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
        return trim("{$this->action} {$this->garment_part}");
    }

    public function getNormalizedNameAttribute(): string
    {
        return strtoupper(trim("{$this->action} {$this->garment_part}"));
    }

    /**
     * Descripción auto-generada a partir de los campos de la operación.
     */
    public function getGeneratedDescriptionAttribute(): string
    {
        if (! empty($this->description)) {
            return $this->description;
        }

        $parts = [strtoupper(trim("{$this->action} {$this->garment_part}"))];

        if ($this->measure && $this->measure > 0) {
            $parts[] = "{$this->measure} cm";
        }
        if ($this->quantity && $this->quantity > 0) {
            $parts[] = (string) $this->quantity;
        }
        if ($this->section?->name) {
            $parts[] = strtoupper($this->section->name);
        }

        return strtoupper(implode(' ', $parts));
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