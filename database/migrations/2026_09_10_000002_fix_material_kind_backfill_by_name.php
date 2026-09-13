<?php

use App\Support\MasterData\MaterialClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige el backfill de la migración 2026_09_01_000003: esa migración fijaba
 * material_kind por id hardcodeado (1 => fabric, 2 => yarn), pero corre ANTES que
 * CategorySeeder inserte los subgrupos "TELA"/"HILAZA" — en cualquier `migrate:fresh
 * --seed` (o instalación nueva) el backfill se ejecuta sobre una tabla todavía vacía
 * y no tiene efecto, dejando TELA/HILAZA con material_kind NULL. Esto rompía en
 * ItemFormContent.vue la condición que decide si el formulario de ítem muestra el
 * selector de base de tela o de hilaza.
 *
 * Esta migración corrige por nombre (subgrupo "TELA"/"HILAZA" dentro del grupo
 * "MATERIA PRIMA"), no por id, para no depender del orden de inserción. Es
 * idempotente y segura de correr en cualquier entorno, incluida una instalación
 * fresca (CategorySeeder ya deja material_kind correcto desde el alta, así que aquí
 * no habría nada que corregir).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->applyByName('TELA', 'fabric');
        $this->applyByName('HILAZA', 'yarn');

        MaterialClassifier::forgetCache();
    }

    public function down(): void
    {
        DB::table('category_sub_groups')
            ->whereIn('name', ['TELA', 'HILAZA'])
            ->whereIn('category_group_id', function ($query) {
                $query->select('id')->from('category_groups')->where('name', 'MATERIA PRIMA');
            })
            ->update(['material_kind' => null]);

        MaterialClassifier::forgetCache();
    }

    private function applyByName(string $subGroupName, string $materialKind): void
    {
        DB::table('category_sub_groups')
            ->where('name', $subGroupName)
            ->whereNull('material_kind')
            ->whereIn('category_group_id', function ($query) {
                $query->select('id')->from('category_groups')->where('name', 'MATERIA PRIMA');
            })
            ->update(['material_kind' => $materialKind]);
    }
};
