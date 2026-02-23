<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;

class Parameter extends Model
{
    protected $table = 'parameters';

    protected $fillable = [
        'nombre',
        'valor',
    ];

    protected $casts = [
        'valor' => 'array',
    ];
}