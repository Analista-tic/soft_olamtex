<?php

namespace App\Models\Production;

use App\Models\Production\ProductSheet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Programmer extends Model
{
    use HasFactory;

    protected $table = 'programmers';

    protected $fillable = [
        'product_sheet_id',
        'reference',
        'sizes',
        'status',
    ];

    protected $casts = [
        'sizes' => 'array',
    ];

    public function productSheet()
    {
        return $this->belongsTo(ProductSheet::class);
    }
}
