<?php

use App\Models\MasterData\Color;
use App\Models\Settings\AuditLog;
use App\Models\Settings\Role;
use App\Models\Settings\User;
use App\Observers\AuditObserver;
use App\Services\Audit\AuditLogService;
use App\Services\Settings\RoleService;
use App\Services\Settings\UserService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AuditCoverageSoftModel extends Model
{
    use SoftDeletes;

    protected $table = 'audit_coverage_soft_models';

    protected $fillable = ['name'];
}

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('never stores password or remember_token in the audit log', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-123')]);
    $user->update(['password' => bcrypt('otra-clave'), 'remember_token' => 'abc123']);

    $logs = AuditLog::query()->where('auditable_type', User::class)->get();

    expect($logs)->not->toBeEmpty();
    foreach ($logs as $log) {
        expect($log->old_values ?? [])->not->toHaveKeys(['password', 'remember_token'])
            ->and($log->new_values ?? [])->not->toHaveKeys(['password', 'remember_token'])
            ->and((string) $log->description)->not->toContain('password')
            ->and((string) $log->description)->not->toContain('remember_token');
    }
});

it('audits models that were previously left out (e.g. Color)', function () {
    Color::create(['code' => 'R01', 'name' => 'Rojo']);

    expect(AuditLog::query()->where('auditable_type', Color::class)->where('action', 'created')->exists())->toBeTrue();
});

it('logs successful and failed logins', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect(AuditLog::query()->where('action', 'login_failed')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'login')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('logs role changes on users and permission changes on roles', function () {
    Role::create(['name' => 'bodega', 'guard_name' => 'web']);
    Permission::create(['name' => 'inventario.ver', 'guard_name' => 'web']);
    $user = User::factory()->create();

    app(UserService::class)->update($user, ['name' => $user->name, 'roles' => ['bodega']]);
    $role = Role::query()->where('name', 'bodega')->firstOrFail();
    app(RoleService::class)->update($role, ['name' => 'bodega', 'permissions' => ['inventario.ver']]);

    $userLog = AuditLog::query()->where('auditable_type', User::class)->where('auditable_id', $user->id)
        ->get()->first(fn (AuditLog $log) => isset($log->new_values['roles']));
    $roleLog = AuditLog::query()->where('auditable_type', Role::class)
        ->get()->first(fn (AuditLog $log) => isset($log->new_values['permissions']));

    expect($userLog?->new_values['roles'])->toBe(['bodega'])
        ->and($roleLog?->new_values['permissions'])->toBe(['inventario.ver']);
});

it('distinguishes restore and force delete of soft-deleted models', function () {
    Schema::create('audit_coverage_soft_models', function (Blueprint $table) {
        $table->temporary();
        $table->id();
        $table->string('name');
        $table->timestamps();
        $table->softDeletes();
    });
    AuditCoverageSoftModel::observe(AuditObserver::class);

    $model = AuditCoverageSoftModel::create(['name' => 'X']);
    $model->delete();
    $model->restore();
    $model->forceDelete();

    $actions = AuditLog::query()->where('auditable_type', AuditCoverageSoftModel::class)
        ->orderBy('id')->pluck('action')->all();

    expect($actions)->toBe(['created', 'deleted', 'restored', 'force_deleted']);
});

it('records bulk operations with the affected ids', function () {
    app(AuditLogService::class)->logBulk(Color::class, 'deleted', [3, 1, 3], null, 'prueba');

    $log = AuditLog::query()->where('action', 'deleted')->latest('id')->firstOrFail();

    expect($log->new_values['ids'])->toBe([3, 1])
        ->and($log->module)->toBe('MasterData')
        ->and($log->description)->toContain('Motivo: prueba');
});

it('scrubs sensitive fields from existing audit logs', function () {
    $id = DB::table('audit_logs')->insertGetId([
        'action' => 'updated',
        'auditable_type' => User::class,
        'auditable_id' => 1,
        'old_values' => json_encode(['name' => 'A', 'password' => 'hash-viejo']),
        'new_values' => json_encode(['name' => 'B', 'password' => 'hash-nuevo']),
        'description' => "Actualizado registro de User: Campo 'name' cambiado de 'A' a 'B'; Campo 'password' cambiado de 'hash-viejo' a 'hash-nuevo'.",
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (require database_path('migrations/2026_09_30_120000_scrub_sensitive_fields_from_audit_logs.php'))->up();

    $row = DB::table('audit_logs')->find($id);

    expect(json_decode($row->old_values, true))->toBe(['name' => 'A'])
        ->and(json_decode($row->new_values, true))->toBe(['name' => 'B'])
        ->and($row->description)->toBe("Actualizado registro de User: Campo 'name' cambiado de 'A' a 'B'.");
});
