<?php

namespace App\Models\Common;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'contact_type',
        'id_type',
        'id_number',
        'dv',
        'full_name',
        'person_type',
        'category',
        'municipality',
        'department',
        'address',
        'email',
        'phone',
        'additional_contacts', // Contactos adicionales
    ];

    // Mutador para asegurarse de que additional_contacts se almacene como JSON
    public function setAdditionalContactsAttribute($value)
    {
        $this->attributes['additional_contacts'] = json_encode($value);
    }

    // Accesor para decodificar additional_contacts al obtenerlo
    public function getAdditionalContactsAttribute($value)
    {
        return json_decode($value, true);
    }
}
