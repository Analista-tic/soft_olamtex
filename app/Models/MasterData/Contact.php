<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model implements \App\Contracts\HasAuditLabel
{
    use SoftDeletes;

    public const CONTACT_TYPES = [
        'proveedor',
        'cliente',
        'cliente_proveedor',
    ];

    public const ID_TYPES = [
        'nit',
        'cc',
        'nit_otro_pais',
        'pp',
        'pep',
        'die',
        'ce',
        'te',
        'ti',
        'rc',
        'nuip',
        'ppt',
    ];

    public const PERSON_TYPES = [
        'persona_natural',
        'persona_juridica',
    ];

    public static function auditLabel(): string
    {
        return 'Contacto';
    }

    protected $fillable = [
        'contact_type',
        'id_type',
        'groups',
        'id_number',
        'dv',
        'full_name',
        'person_type',
        'municipality',
        'department',
        'address',
        'email',
        'phone',
        'additional_contacts',
        'branches',
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

    // Mutador para asegurarse de que groups se almacene como JSON
    public function setGroupsAttribute($value)
    {
        $this->attributes['groups'] = json_encode($value);
    }

    // Accesor para decodificar groups al obtenerlo
    public function getGroupsAttribute($value)
    {
        return json_decode($value, true);
    }

    // Mutador para asegurarse de que branches se almacene como JSON
    public function setBranchesAttribute($value)
    {
        $this->attributes['branches'] = json_encode($value);
    }

    // Accesor para decodificar branches al obtenerlo
    public function getBranchesAttribute($value)
    {
        return json_decode($value, true);
    }
}
