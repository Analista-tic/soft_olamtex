<?php

namespace App\Services\Settings;

use App\Models\Settings\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
class UserService
{
    public function __construct(
        protected AuditLogService $auditLog
    ) {}

    private function syncRolesAudited(User $user, array $roles): void
    {
        $before = $user->roles()->pluck('name')->sort()->values()->all();
        $user->syncRoles($roles);
        $after = $user->roles()->pluck('name')->sort()->values()->all();

        if ($before === $after) {
            return;
        }

        $this->auditLog->logEvent(
            action: 'updated',
            description: sprintf(
                "Roles del usuario '%s' actualizados: [%s] → [%s].",
                $user->name,
                implode(', ', $before),
                implode(', ', $after)
            ),
            subject: $user,
            old: ['roles' => $before],
            new: ['roles' => $after],
        );
    }

    public function findOrFail($id): User
    {
        return User::with('roles.permissions')->findOrFail($id);
    }

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $roles = $data['roles'] ?? [];
            unset($data['roles']);

            if (isset($data['password'])) {
                $data['password'] = bcrypt($data['password']);
            }

            $user = User::create($data);
            if (!empty($roles)) {
                $this->syncRolesAudited($user, $roles);
            }

            return $user->fresh();
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $roles = $data['roles'] ?? null;
            if (array_key_exists('roles', $data)) {
                unset($data['roles']);
            }

            if (isset($data['password']) && $data['password'] !== null && $data['password'] !== '') {
                $data['password'] = bcrypt($data['password']);
            } else {
                unset($data['password']);
            }

            $user->update($data);

            if ($roles !== null) {
                $this->syncRolesAudited($user, $roles);
            }

            return $user->fresh();
        });
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = User::with('roles');

        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['area_id'])) {
            $query->where('area_id', $filters['area_id']);
        }

        if (!empty($filters['sortBy'])) {
            $query->orderBy($filters['sortBy'], $filters['sortDirection'] ?? 'asc');
        }

        return $query->paginate($perPage);
    }
}
