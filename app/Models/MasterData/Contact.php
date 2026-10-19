<?php

namespace App\Models\MasterData;

use App\Models\Concerns\NormalizesAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model implements \App\Contracts\HasAuditLabel
{
    use HasFactory, SoftDeletes, NormalizesAttributes;

    /**
     * Reglas de normalización al guardar (ver NormalizesAttributes).
     * contact_type / id_type / person_type y los JSON quedan fuera: se guardan tal cual.
     * full_name es persona o razón social ("S.A.S"): se conserva lo escrito, solo se limpian espacios.
     *
     * @var array<string, string>
     */
    protected array $normalizedAttributes = [
        'full_name' => 'normalizeDisplayText',
        'municipality' => 'normalizeDisplayText',
        'department' => 'normalizeDisplayText',
        'address' => 'freeText',
        'email' => 'email',
        'id_number' => 'code',
    ];

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
