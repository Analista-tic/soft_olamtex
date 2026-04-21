<?php

namespace App\Helpers;

use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Builder;

class ScopeHelper
{
    /**
     * Aplica el filtro de visibilidad por alcance a una query Builder.
     *
     * Orden de prioridad (el primero que aplique gana):
     *  1. $allPermission  → sin filtro (ve todo)
     *  2. $areaPermission → whereIn sobre usuarios del mismo área
     *  3. $ownPermission  → where por usuario autenticado
     *  4. Ninguno         → where con -1 (devuelve vacío, sin error)
     *
     * @param  Builder     $query
     * @param  User|null   $user            Usuario autenticado
     * @param  string|null $ownPermission   Permiso "ver propios ..."
     * @param  string|null $areaPermission  Permiso "ver area ..."  (null = no existe)
     * @param  string|null $allPermission   Permiso "ver todos ..."
     * @param  string      $ownerField      Campo de autoría en la tabla (default: created_by_user_id)
     * @return Builder
     */
    public static function apply(
        Builder $query,
        ?User $user,
        ?string $ownPermission,
        ?string $areaPermission,
        ?string $allPermission,
        string $ownerField = 'created_by_user_id'
    ): Builder {
        if ($user === null) {
            return $query->where($ownerField, -1);
        }

        // 1. Ver todos — sin restricción
        if ($allPermission && $user->hasPermissionTo($allPermission)) {
            return $query;
        }

        // 2. Ver área — usuarios del mismo área
        if ($areaPermission && $user->hasPermissionTo($areaPermission)) {
            if ($user->area_id) {
                $areaUserIds = User::where('area_id', $user->area_id)->pluck('id');
                return $query->whereIn($ownerField, $areaUserIds);
            }
            // Sin área asignada → cae a ver propios
            return $query->where($ownerField, $user->id);
        }

        // 3. Ver propios
        if ($ownPermission && $user->hasPermissionTo($ownPermission)) {
            return $query->where($ownerField, $user->id);
        }

        // 4. Sin permiso de visibilidad → devuelve vacío
        return $query->where($ownerField, -1);
    }
}
