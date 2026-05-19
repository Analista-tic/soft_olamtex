<?php

namespace App\Models\Production;

use App\Models\Concerns\HasAuditLabel;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TechnicalSheetOperationHistory extends Model
{
    use HasFactory, HasAuditLabel;

    protected $table = 'technical_sheet_operation_history';

    protected $fillable = [
        'technical_sheet_id',
        'technical_sheet_operation_id',
        'event_type',
        'changed_by_user_id',
        'reason',
        'before_sequence',
        'before_operation_id',
        'before_machine_id',
        'before_quantity',
        'before_sam_base',
        'before_machine_supplement_factor',
        'before_fabric_base_factor',
        'before_operator_efficiency_factor',
        'before_sam_total_unitario',
        'before_sam_total_operacion',
        'before_sam_total_lote',
        'before_quantity_progressed',
        'before_status',
        'after_sequence',
        'after_operation_id',
        'after_machine_id',
        'after_quantity',
        'after_sam_base',
        'after_machine_supplement_factor',
        'after_fabric_base_factor',
        'after_operator_efficiency_factor',
        'after_sam_total_unitario',
        'after_sam_total_operacion',
        'after_sam_total_lote',
        'after_quantity_progressed',
        'after_status',
    ];

    protected $casts = [
        'before_sequence' => 'integer',
        'before_operation_id' => 'integer',
        'before_machine_id' => 'integer',
        'before_quantity' => 'integer',
        'before_sam_base' => 'float',
        'before_machine_supplement_factor' => 'float',
        'before_fabric_base_factor' => 'float',
        'before_operator_efficiency_factor' => 'float',
        'before_sam_total_unitario' => 'float',
        'before_sam_total_operacion' => 'float',
        'before_sam_total_lote' => 'float',
        'before_quantity_progressed' => 'integer',
        'after_sequence' => 'integer',
        'after_operation_id' => 'integer',
        'after_machine_id' => 'integer',
        'after_quantity' => 'integer',
        'after_sam_base' => 'float',
        'after_machine_supplement_factor' => 'float',
        'after_fabric_base_factor' => 'float',
        'after_operator_efficiency_factor' => 'float',
        'after_sam_total_unitario' => 'float',
        'after_sam_total_operacion' => 'float',
        'after_sam_total_lote' => 'float',
        'after_quantity_progressed' => 'integer',
    ];

    public function technicalSheet()
    {
        return $this->belongsTo(TechnicalSheet::class);
    }

    public function technicalSheetOperation()
    {
        return $this->belongsTo(TechnicalSheetOperation::class, 'technical_sheet_operation_id');
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
