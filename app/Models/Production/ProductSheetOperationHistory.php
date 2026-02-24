<?php

namespace App\Models\Production;

use App\Models\Concerns\HasAuditLabel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSheetOperationHistory extends Model
{
    use HasFactory, HasAuditLabel;

    protected $table = 'product_sheet_operation_history';

    protected $fillable = [
        'product_sheet_operation_id',
        'sam_base_anterior',
        'sam_base_nuevo',
        'machine_supplement_factor_anterior',
        'machine_supplement_factor_nuevo',
        'fabric_base_factor_anterior',
        'fabric_base_factor_nuevo',
        'operator_efficiency_factor_anterior',
        'operator_efficiency_factor_nuevo',
        'sam_total_unitario_anterior',
        'sam_total_unitario_nuevo',
        'sam_total_operacion_anterior',
        'sam_total_operacion_nuevo',
        'motivo',
        'usuario_id',
    ];

    protected $casts = [
        'sam_base_anterior' => 'float',
        'sam_base_nuevo' => 'float',
        'machine_supplement_factor_anterior' => 'float',
        'machine_supplement_factor_nuevo' => 'float',
        'fabric_base_factor_anterior' => 'float',
        'fabric_base_factor_nuevo' => 'float',
        'operator_efficiency_factor_anterior' => 'float',
        'operator_efficiency_factor_nuevo' => 'float',
        'sam_total_unitario_anterior' => 'float',
        'sam_total_unitario_nuevo' => 'float',
        'sam_total_operacion_anterior' => 'float',
        'sam_total_operacion_nuevo' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function productSheetOperation()
    {
        return $this->belongsTo(ProductSheetOperation::class, 'product_sheet_operation_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
