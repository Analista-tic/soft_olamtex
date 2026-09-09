<?php

use App\Models\Settings\Setting;
use App\Services\MasterData\ParameterUsageService;
use App\Services\Settings\SettingService;
use Database\Seeders\SettingsFieldParametersSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const REMOVED_SETTING_KEYS = ['base_tela', 'base_hilaza', 'fabrics', 'fabric_bases', 'yarn_bases', 'fabric_colors'];

it('leaves no duplicated material catalog in settings', function () {
    foreach (REMOVED_SETTING_KEYS as $key) {
        expect(Setting::where('key', $key)->exists())->toBeFalse("La clave '{$key}' sigue en settings");
    }
});

it('does not recreate the removed keys when the seeder runs again', function () {
    (new SettingsFieldParametersSeeder)->run();

    foreach (REMOVED_SETTING_KEYS as $key) {
        expect(Setting::where('key', $key)->exists())->toBeFalse("El seeder volvió a crear '{$key}'");
    }
});

it('keeps the other seeded parameters and fields intact', function () {
    (new SettingsFieldParametersSeeder)->run();

    expect(Setting::where('key', 'fabric_providers')->exists())->toBeTrue()
        ->and(Setting::where('key', 'talla')->exists())->toBeTrue();
});

it('no longer blocks deleting a fabric_bases parameter as if it were still in use', function () {
    // Antes, ParameterUsageService declaraba fabric_bases "en uso" aunque ningún
    // consumidor lo leía: la pantalla de Parámetros impedía borrarlo.
    $parameter = Setting::create([
        'kind' => Setting::KIND_PARAMETER,
        'key' => 'fabric_bases',
        'label' => 'Bases de tela (residuo)',
        'value' => [],
        'status' => true,
    ]);

    $result = app(ParameterUsageService::class)->inspect($parameter);

    expect($result['can_delete'])->toBeTrue()
        ->and($result['usages'])->toBeEmpty();

    $parameter->delete();
});

it('returns an empty collection instead of failing when reading a removed catalog', function () {
    expect(app(SettingService::class)->getCatalogOptions('fabric_bases'))->toBeEmpty();
});

it('keeps an archive table so the removal can be rolled back', function () {
    // No se asevera que haya filas archivadas: en una base recién montada no existían
    // los duplicados que borrar. Lo que debe existir siempre es el mecanismo de
    // reversión, con las columnas necesarias para restaurar la fila tal cual estaba.
    expect(Schema::hasTable('settings_archive'))->toBeTrue()
        ->and(Schema::hasColumns('settings_archive', ['id', 'kind', 'key', 'label', 'value', 'options', 'archived_at']))->toBeTrue();
});

it('archives a duplicated row before deleting it', function () {
    DB::table('settings_archive')->where('key', 'fabrics')->delete();

    $id = DB::table('settings')->insertGetId([
        'kind' => Setting::KIND_PARAMETER,
        'key' => 'fabrics',
        'label' => 'Telas (residuo)',
        'value' => json_encode([]),
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Re-ejecuta el paso de archivado+borrado de la migración sobre esa fila.
    $migration = require database_path('migrations/2026_09_01_000002_archive_and_delete_duplicated_settings.php');
    $migration->up();

    expect(DB::table('settings')->where('id', $id)->exists())->toBeFalse()
        ->and(DB::table('settings_archive')->where('id', $id)->exists())->toBeTrue();

    DB::table('settings_archive')->where('id', $id)->delete();
});

it('archives a fabric_colors row before deleting it', function () {
    DB::table('settings_archive')->where('key', 'fabric_colors')->delete();

    $id = DB::table('settings')->insertGetId([
        'kind' => Setting::KIND_PARAMETER,
        'key' => 'fabric_colors',
        'label' => 'Colores de tela (residuo)',
        'value' => json_encode([]),
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_09_02_000001_archive_and_delete_fabric_colors_setting.php');
    $migration->up();

    expect(DB::table('settings')->where('id', $id)->exists())->toBeFalse()
        ->and(DB::table('settings_archive')->where('id', $id)->exists())->toBeTrue();

    DB::table('settings_archive')->where('id', $id)->delete();
});

it('no longer exposes getCatalogOptionsWithCode, dead code removed with fabric_colors', function () {
    expect(method_exists(SettingService::class, 'getCatalogOptionsWithCode'))->toBeFalse();
});

it('keeps serving other settings parameters after removing fabric_colors', function () {
    (new SettingsFieldParametersSeeder)->run();

    expect(app(SettingService::class)->getCatalogOptions('fabric_providers'))->not->toBeEmpty();
});
