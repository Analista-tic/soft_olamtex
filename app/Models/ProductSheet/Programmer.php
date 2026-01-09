<?php

namespace App\Models\ProductSheet;

use App\Models\Inventory\FabricOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Programmer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * La tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'programmers';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array
     */
    protected $fillable = [
        'product_sheet_id',
        'season',
        'destination_id',
        'priority',
        'units',
        'ecommerce_units',
        'status',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'ecommerce_units' => 'array', // Convertir JSON a array automáticamente
    ];

    /**
     * Relación con el modelo `ProductSheet`.
     */
    public function productSheet()
    {
        return $this->belongsTo(ProductSheet::class);
    }

    // Relación destination eliminada - ahora se obtiene desde parameters
}
