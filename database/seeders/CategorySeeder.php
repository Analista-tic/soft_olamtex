<?php

namespace Database\Seeders;

use App\Support\MasterData\MaterialClassifier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET NAMES utf8mb4');
        DB::statement('SET CHARACTER SET utf8mb4');

        $now = now();

        $groups = [
            ['name' => 'MATERIA PRIMA',    'created_at' => $now, 'updated_at' => $now],
            ['name' => 'INSUMOS',          'created_at' => $now, 'updated_at' => $now],
            ['name' => 'SUMINISTROS',      'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ADITAMENTOS',      'created_at' => $now, 'updated_at' => $now],
            ['name' => 'HERRAMIENTAS',     'created_at' => $now, 'updated_at' => $now],
            ['name' => 'REPUESTOS',        'created_at' => $now, 'updated_at' => $now],
            ['name' => 'PAPELERÍA Y ASEO', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'EPP',              'created_at' => $now, 'updated_at' => $now],
            ['name' => 'SERVICIOS',        'created_at' => $now, 'updated_at' => $now],
            ['name' => 'MAQUINARIA',       'created_at' => $now, 'updated_at' => $now],
            ['name' => 'PRODUCTO TERMINADO', 'created_at' => $now, 'updated_at' => $now],
            // Agregado manualmente en producción el 2026-04-21 (no estaba en el
            // seed inicial); se replica aquí para que instalaciones nuevas queden
            // alineadas con el catálogo real.
            ['name' => 'MANTENIMIENTO',    'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($groups as $index => &$group) {
            $group['code'] = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
        }
        unset($group);

        DB::table('category_groups')->insert($groups);

        $groupIds = DB::table('category_groups')->pluck('id', 'name');

        $subGroups = [

            // ─── MATERIA PRIMA ────────────────────────────────────────────
            ['name' => 'TELA',   'category_group_id' => $groupIds['MATERIA PRIMA'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'HILAZA', 'category_group_id' => $groupIds['MATERIA PRIMA'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'HILO',   'category_group_id' => $groupIds['MATERIA PRIMA'], 'created_at' => $now, 'updated_at' => $now],

            // ─── INSUMOS ──────────────────────────────────────────────────
            ['name' => 'ACCESORIOS',  'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ADORNO',      'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'AGUJA',       'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'APLIQUE',     'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'BOLSA',       'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'BOTÓN',       'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CIERRE',      'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CINTA',       'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CÓDIGO',      'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CORDÓN',      'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CORREA',      'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CREMALLERA',  'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ELÁSTICO',    'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ENCAJE',      'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ETIQUETA',    'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'GARRA',       'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'GENERAL',     'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'HEBILLA',     'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'HERRAJE',     'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'HILADILLA',   'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'HILAZA',      'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'HILO',        'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'INSTRUCCIÓN', 'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'MARQUILLA',   'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'OJALETE',     'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'PLACA',       'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'REMACHE',     'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'VARIOS',      'category_group_id' => $groupIds['INSUMOS'], 'created_at' => $now, 'updated_at' => $now],

            // ─── SUMINISTROS ──────────────────────────────────────────────
            ['name' => 'CAJA',        'category_group_id' => $groupIds['SUMINISTROS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CINTA',       'category_group_id' => $groupIds['SUMINISTROS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CÓDIGO',      'category_group_id' => $groupIds['SUMINISTROS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CONSUMIBLES', 'category_group_id' => $groupIds['SUMINISTROS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'EMPAQUE',     'category_group_id' => $groupIds['SUMINISTROS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ROLLO',       'category_group_id' => $groupIds['SUMINISTROS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'VARIOS',      'category_group_id' => $groupIds['SUMINISTROS'], 'created_at' => $now, 'updated_at' => $now],

            // ─── ADITAMENTOS ──────────────────────────────────────────────
            ['name' => 'ACCESORIOS MAQUINA', 'category_group_id' => $groupIds['ADITAMENTOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CUCHILLA',           'category_group_id' => $groupIds['ADITAMENTOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'HILO',               'category_group_id' => $groupIds['ADITAMENTOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'PAÑO',               'category_group_id' => $groupIds['ADITAMENTOS'], 'created_at' => $now, 'updated_at' => $now],

            // ─── HERRAMIENTAS ─────────────────────────────────────────────
            ['name' => 'CHEQUEADOR',          'category_group_id' => $groupIds['HERRAMIENTAS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'DESTORNILLADOR',      'category_group_id' => $groupIds['HERRAMIENTAS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ENCINTADOR',          'category_group_id' => $groupIds['HERRAMIENTAS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'GRAPADORA',           'category_group_id' => $groupIds['HERRAMIENTAS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'HERRAMIENTAS VARIAS', 'category_group_id' => $groupIds['HERRAMIENTAS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'MEDICIÓN',            'category_group_id' => $groupIds['HERRAMIENTAS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'PISTOLA',             'category_group_id' => $groupIds['HERRAMIENTAS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'TIJERA',              'category_group_id' => $groupIds['HERRAMIENTAS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'TROQUEL',             'category_group_id' => $groupIds['HERRAMIENTAS'], 'created_at' => $now, 'updated_at' => $now],

            // ─── REPUESTOS ────────────────────────────────────────────────
            ['name' => 'CAJA',    'category_group_id' => $groupIds['REPUESTOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CORREA',  'category_group_id' => $groupIds['REPUESTOS'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'GENERAL', 'category_group_id' => $groupIds['REPUESTOS'], 'created_at' => $now, 'updated_at' => $now],

            // ─── PAPELERÍA Y ASEO ─────────────────────────────────────────
            ['name' => 'BOLSA DE BASURA',    'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'BOLÍGRAFO',          'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CARPETA',            'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CINTA ADHESIVA',     'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CONSUMIBLES',        'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'COSEDORA',           'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'DESINFECTANTE',      'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ENVASE',             'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ESCRITORIO',         'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ESPONJA',            'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'JABÓN Y DETERGENTE', 'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'LIMPIEZA PISOS',     'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'MARCADOR',           'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'RESMA',              'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'TALONARIO',          'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'TECNOLOGÍA',         'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'TIJERA',             'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'TINTA',              'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'TOALLA',             'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'VARIOS ASEO',        'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'VARIOS PAPELERÍA',   'category_group_id' => $groupIds['PAPELERÍA Y ASEO'], 'created_at' => $now, 'updated_at' => $now],

            // ─── EPP ──────────────────────────────────────────────────────
            ['name' => 'BOTIQUÍN',            'category_group_id' => $groupIds['EPP'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CALZADO',             'category_group_id' => $groupIds['EPP'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'GUANTES',             'category_group_id' => $groupIds['EPP'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'PRIMEROS AUXILIOS',   'category_group_id' => $groupIds['EPP'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'PROTECCIÓN AUDITIVA', 'category_group_id' => $groupIds['EPP'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'PROTECCIÓN VISUAL',   'category_group_id' => $groupIds['EPP'], 'created_at' => $now, 'updated_at' => $now],

            // ─── SERVICIOS ────────────────────────────────────────────────
            ['name' => 'CONFECCIÓN', 'category_group_id' => $groupIds['SERVICIOS'], 'created_at' => $now, 'updated_at' => $now],

            // ─── MAQUINARIA ───────────────────────────────────────────────
            ['name' => 'MÁQUINA', 'category_group_id' => $groupIds['MAQUINARIA'], 'created_at' => $now, 'updated_at' => $now],

            // ─── PRODUCTO TERMINADO ───────────────────────────────────────
            ['name' => 'BERMUDA',  'category_group_id' => $groupIds['PRODUCTO TERMINADO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'BLUSA',    'category_group_id' => $groupIds['PRODUCTO TERMINADO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'BUZO',     'category_group_id' => $groupIds['PRODUCTO TERMINADO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CHALECO',  'category_group_id' => $groupIds['PRODUCTO TERMINADO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'PANTALÓN', 'category_group_id' => $groupIds['PRODUCTO TERMINADO'], 'created_at' => $now, 'updated_at' => $now],

            // ─── MANTENIMIENTO ────────────────────────────────────────────
            ['name' => 'TECNOLÓGICO', 'category_group_id' => $groupIds['MANTENIMIENTO'], 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'HARDWARE',    'category_group_id' => $groupIds['MANTENIMIENTO'], 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($subGroups as $index => &$subGroup) {
            $subGroup['code'] = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
        }
        unset($subGroup);

        DB::table('category_sub_groups')->insert($subGroups);

        // TELA/HILAZA llevan material_kind desde el alta: son los subgrupos que
        // ItemFormContent.vue usa para decidir si el ítem selecciona una base de tela
        // o de hilaza. Va aparte del insert masivo de arriba porque mezclar filas con
        // distintas claves en un solo insert() rompe el conteo de columnas de la
        // consulta generada. Antes esto se backfillaba en una migración por id
        // hardcodeado, que corre antes de que este seeder exista y nunca aplicaba.
        DB::table('category_sub_groups')
            ->where('category_group_id', $groupIds['MATERIA PRIMA'])
            ->where('name', 'TELA')
            ->update(['material_kind' => 'fabric']);
        DB::table('category_sub_groups')
            ->where('category_group_id', $groupIds['MATERIA PRIMA'])
            ->where('name', 'HILAZA')
            ->update(['material_kind' => 'yarn']);

        MaterialClassifier::forgetCache();
    }
}
