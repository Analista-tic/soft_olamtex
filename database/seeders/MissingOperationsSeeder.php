<?php

namespace Database\Seeders;

use App\Models\Production\Operation;
use Illuminate\Database\Seeder;

/**
 * Inserta las 195 operaciones del CSV que faltaban en la BD.
 * Usa updateOrCreate por codigo para ser idempotente.
 */
class MissingOperationsSeeder extends Seeder
{
    public function run(): void
    {
        // product_reference → product_sheet id:
        // 301594→24, 301490→23, 301618→22, 301701→21, 301684→20, 301710→19
        // 301708→17, 301619→16, 301494→5,  301700→6,  301648→7,  301719→9
        // 301604→10, 301588→11, 301639→3,  301640→13, 301661→14, 301660→15
        // 301689→2,  301630→12, 301662→4

        // [codigo, action, garment_part, anexo, section_id, measure, quantity, sam_base, sheet_id, machine_id]
        $operations = [
            // ── 301594 (id=24) ──────────────────────────────────────────────
            ['OP-0045', 'PLANCHAR',              'BORDES',              'CUELLO',              1,  5,    1, 0.24, 24,  1],
            ['OP-0060', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07, 24,  1],
            ['OP-0063', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.05, 24,  1],

            // ── 301490 (id=23) ──────────────────────────────────────────────
            ['OP-0066', 'QUITAR',                'DESPERDICIO',         'ESPALDA',             1,  null, 1, 0.45, 23,  1],
            ['OP-0067', 'QUITAR',                'DESPERDICIO',         'FRENTE',              1,  null, 1, 0.51, 23,  1],
            ['OP-0072', 'VOLTEAR E INSPECCIONAR','PRENDA COMPLETA',     null,                  3,  null, 1, 0.57, 23,  1],
            ['OP-0073', 'DOBLAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.31, 23,  1],
            ['OP-0074', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07, 23,  1],
            ['OP-0075', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.15, 23,  1],
            ['OP-0076', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.20, 23,  1],
            ['OP-0077', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.05, 23,  1],

            // ── 301618 (id=22) ──────────────────────────────────────────────
            ['OP-0105', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07, 22,  1],
            ['OP-0106', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07, 22,  1],
            ['OP-0107', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.20, 22,  1],
            ['OP-0108', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09, 22,  1],

            // ── 301701 (id=21) ──────────────────────────────────────────────
            ['OP-0127', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07, 21,  1],
            ['OP-0128', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07, 21,  1],
            ['OP-0130', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09, 21,  1],

            // ── 301684 (id=20) ──────────────────────────────────────────────
            ['OP-0136', 'MEDIR Y CORTAR',        'ELASTICO',            'PRETINA',             1,  85,   1, 0.23, 20,  1],
            ['OP-0147', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07, 20,  1],
            ['OP-0148', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07, 20,  1],
            ['OP-0149', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.13, 20,  1],
            ['OP-0150', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09, 20,  1],

            // ── 301710 (id=19) ──────────────────────────────────────────────
            ['OP-0152', 'CERRAR',                'TIRO',                'TRASERO',             1,  32,   1, 0.23, 19,  5],
            ['OP-0153', 'MEDIR Y CORTAR',        'ELASTICO',            'PRETINA',             1,  85,   1, 0.23, 19,  1],
            ['OP-0159', 'REVISAR',               'PRENDA COMPLETA',     null,                  3,  null, 1, 0.42, 19,  1],
            ['OP-0160', 'INSERTAR',              'CORDON',              null,                  3,  null, 1, 0.45, 19,  1],
            ['OP-0161', 'DOBLAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.45, 19,  1],
            ['OP-0162', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07, 19,  1],
            ['OP-0163', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07, 19,  1],
            ['OP-0164', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.13, 19,  1],
            ['OP-0165', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09, 19,  1],

            // ── 301708 (id=17) ──────────────────────────────────────────────
            ['OP-0189', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07, 17,  1],
            ['OP-0190', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07, 17,  1],
            ['OP-0191', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.13, 17,  1],
            ['OP-0192', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09, 17,  1],
            ['OP-0193', 'FILETEAR',              'RUEDO',               'COMPLEMENTO',         1,  33,   1, 0.36, 17,  3],
            ['OP-0194', 'DOBLADILLAR',           'RUEDO',               'COMPLEMENTO',         1,  33,   1, 0.85, 17,  2],
            ['OP-0195', 'FIJAR',                 'COMPLEMENTO',         'FALDA',               1,  42,   1, 0.37, 17,  2],
            ['OP-0196', 'CERRAR',                'TIRO',                'DELANTERO',           1,  22,   1, 0.23, 17,  5],
            ['OP-0197', 'CERRAR',                'TIRO',                'TRASERO',             1,  32,   1, 0.23, 17,  5],
            ['OP-0198', 'MEDIR Y CORTAR',        'ELASTICO',            'PRETINA',             1,  85,   1, 0.23, 17,  1],
            ['OP-0199', 'FIJAR',                 'ELASTICO',            'PRETINA',             1,  85,   1, 0.43, 17,  2],
            ['OP-0200', 'CERRAR',                'PRETINA',             'EN CONTORNO',         1,  85,   1, 1.01, 17, 15],
            ['OP-0201', 'CERRAR',                'LATERAL',             null,                  2, 102,   2, 0.50, 17,  5],
            ['OP-0202', 'CERRAR',                'ENTREPIERNA',         null,                  2, 146,   1, 0.29, 17,  3],
            ['OP-0203', 'FILETEAR',              'RUEDO',               'BOTA',                2,  33,   2, 0.35, 17,  5],
            ['OP-0204', 'DOBLADILLAR',           'RUEDO',               'BOTA',                2,  33,   2, 0.85, 17,  2],
            ['OP-0205', 'PRENDER',               'PRETINA',             'CON INST LAVADO',     2,  85,   1, 0.58, 17,  4],
            ['OP-0206', 'REVISAR',               'PRENDA COMPLETA',     null,                  3,  null, 1, 0.42, 17,  1],
            ['OP-0207', 'PLANCHAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.45, 17, 14],
            ['OP-0208', 'DOBLAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.45, 17,  1],
            ['OP-0209', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07, 17,  1],
            ['OP-0210', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07, 17,  1],
            ['OP-0211', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.13, 17,  1],
            ['OP-0212', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09, 17,  1],

            // ── 301619 (id=16) ──────────────────────────────────────────────
            ['OP-0216', 'UNIR Y VOLTEAR',        'FAJON',               null,                  1, 116,   1, 0.36, 16,  2],
            ['OP-0217', 'UNIR Y VOLTEAR',        'PUÃO',                null,                  1,  27,   2, 0.46, 16,  2],
            ['OP-0227', 'PRENDER',               'PUÃO',                null,                  2,  27,   2, 0.85, 16,  3],
            ['OP-0236', 'VOLTEAR',               'PRENDA COMPLETA',     null,                  3,  null, 1, 0.55, 16,  1],
            ['OP-0237', 'REVISAR',               'PRENDA COMPLETA',     null,                  3,  null, 1, 0.64, 16,  1],
            ['OP-0238', 'INSERTAR',              'CORDON',              null,                  3,  null, 1, 0.45, 16,  1],
            ['OP-0240', 'PLANCHAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.70, 16, 14],
            ['OP-0241', 'DOBLAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.64, 16,  1],
            ['OP-0242', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07, 16,  1],
            ['OP-0243', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07, 16,  1],
            ['OP-0244', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.20, 16,  1],
            ['OP-0245', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09, 16,  1],

            // ── 301494 (id=5) ───────────────────────────────────────────────
            ['OP-0255', 'PLANCHAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.45,  5, 14],
            ['OP-0257', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07,  5,  1],
            ['OP-0258', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07,  5,  1],
            ['OP-0259', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.13,  5,  1],
            ['OP-0260', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09,  5,  1],

            // ── 301700 (id=6) ───────────────────────────────────────────────
            ['OP-0263', 'CERRAR',                'TIRO',                'TRASERO',             1,  32,   1, 0.23,  6,  5],
            ['OP-0276', 'DOBLAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.64,  6,  1],
            ['OP-0277', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07,  6,  1],
            ['OP-0278', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07,  6,  1],
            ['OP-0279', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.13,  6,  1],
            ['OP-0280', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09,  6,  1],

            // ── 301648 (id=7) ───────────────────────────────────────────────
            ['OP-0296', 'REVISAR',               'PRENDA COMPLETA',     null,                  3,  null, 1, 1.09,  7,  1],
            ['OP-0297', 'PLANCHAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.70,  7, 14],
            ['OP-0299', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07,  7,  1],
            ['OP-0300', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07,  7,  1],
            ['OP-0301', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.13,  7,  1],
            ['OP-0302', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09,  7,  1],

            // ── 301719 (id=9) ───────────────────────────────────────────────
            ['OP-0303', 'UNIR',                  'ELASTICO',            null,                  1,  85,   1, 0.09,  9,  2],
            ['OP-0305', 'CERRAR',                'TIRO',                'DELANTERO',           1,  22,   1, 0.23,  9,  5],
            ['OP-0306', 'CERRAR',                'TIRO',                'TRASERO',             1,  32,   1, 0.23,  9,  5],
            ['OP-0315', 'DOBLAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.55,  9,  1],
            ['OP-0316', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07,  9,  1],
            ['OP-0317', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07,  9,  1],
            ['OP-0318', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.13,  9,  1],
            ['OP-0319', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09,  9,  1],

            // ── 301604 (id=10) ──────────────────────────────────────────────
            ['OP-0327', 'DOBLADILLAR',           'RUEDO',               null,                  2,  70,   1, 0.69, 10,  7],
            ['OP-0329', 'REVISAR',               'PRENDA COMPLETA',     null,                  3,  null, 1, 0.57, 10,  1],
            ['OP-0330', 'DOBLAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.59, 10,  1],
            ['OP-0331', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07, 10,  1],
            ['OP-0332', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07, 10,  1],
            ['OP-0333', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.13, 10,  1],
            ['OP-0334', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09, 10,  1],

            // ── 301588 (id=11) ──────────────────────────────────────────────
            ['OP-0340', 'DOBLADILLAR',           'RUEDO',               null,                  2,  70,   1, 0.69, 11,  7],
            ['OP-0341', 'REVISAR',               'PRENDA COMPLETA',     null,                  3,  null, 1, 0.57, 11,  1],
            ['OP-0343', 'DOBLAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.59, 11,  1],
            ['OP-0344', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07, 11,  1],
            ['OP-0345', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07, 11,  1],
            ['OP-0346', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.13, 11,  1],
            ['OP-0347', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09, 11,  1],

            // ── 301639 (id=3) ───────────────────────────────────────────────
            ['OP-0348', 'UNIR Y VOLTEAR',        'PUÃO',                null,                  1,  27,   2, 0.46,  3,  2],
            ['OP-0349', 'UNIR Y VOLTEAR',        'FAJON',               null,                  1, 116,   1, 0.36,  3,  2],
            ['OP-0356', 'PRENDER',               'MANGA LARGA',         null,                  2,  58,   2, 0.58,  3,  5],
            ['OP-0357', 'CERRAR',                'LATERAL',             null,                  2,  90,   2, 0.83,  3,  5],
            ['OP-0363', 'REVISAR',               'PRENDA COMPLETA',     null,                  3,  null, 1, 0.64,  3,  1],
            ['OP-0364', 'PLANCHAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.70,  3, 14],
            ['OP-0365', 'DOBLAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.64,  3,  1],
            ['OP-0366', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07,  3,  1],
            ['OP-0367', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07,  3,  1],
            ['OP-0368', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.13,  3,  1],
            ['OP-0369', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09,  3,  1],

            // ── 301640 (id=13) ──────────────────────────────────────────────
            ['OP-0385', 'REVISAR',               'PRENDA COMPLETA',     null,                  3,  null, 1, 0.57, 13,  1],
            ['OP-0387', 'PLANCHAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.43, 13, 14],
            ['OP-0388', 'DOBLAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.59, 13,  1],
            ['OP-0389', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07, 13,  1],
            ['OP-0390', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07, 13,  1],
            ['OP-0391', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.13, 13,  1],
            ['OP-0392', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09, 13,  1],

            // ── 301661 (id=14) ──────────────────────────────────────────────
            ['OP-0393', 'CERRAR',                'BOLSILLO',            'DELANTERO',           1,  32,   2, 0.36, 14,  3],
            ['OP-0397', 'CERRAR',                'TIRO',                'DELANTERO',           1,  22,   1, 0.24, 14,  3],
            ['OP-0398', 'CERRAR',                'TIRO',                'TRASERO',             1,  32,   1, 0.24, 14,  3],
            ['OP-0399', 'MEDIR Y CORTAR',        'ELASTICO',            'PRETINA',             1,  85,   1, 0.23, 14,  1],
            ['OP-0400', 'UNIR',                  'ELASTICO',            null,                  1,  85,   1, 0.09, 14,  2],
            ['OP-0401', 'UNIR',                  'PRETINA',             'CON ELASTICO',        1,  85,   2, 0.26, 14,  2],
            ['OP-0407', 'REVISAR',               'PRENDA COMPLETA',     null,                  3,  null, 1, 0.50, 14,  1],
            ['OP-0408', 'INSERTAR',              'CORDON',              null,                  3,  null, 1, 0.45, 14,  1],
            ['OP-0409', 'PLANCHAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.70, 14, 14],
            ['OP-0410', 'DOBLAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.55, 14,  1],
            ['OP-0411', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07, 14,  1],
            ['OP-0412', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07, 14,  1],
            ['OP-0413', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.13, 14,  1],
            ['OP-0414', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09, 14,  1],

            // ── 301660 (id=15) ──────────────────────────────────────────────
            ['OP-0424', 'REVISAR',               'PRENDA COMPLETA',     null,                  3,  null, 1, 0.57, 15,  1],
            ['OP-0425', 'PLANCHAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.43, 15, 14],
            ['OP-0426', 'DOBLAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.59, 15,  1],
            ['OP-0427', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07, 15,  1],
            ['OP-0428', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07, 15,  1],
            ['OP-0429', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.13, 15,  1],
            ['OP-0430', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09, 15,  1],

            // ── 301689 (id=2) ───────────────────────────────────────────────
            ['OP-0434', 'CERRAR',                'TIRO',                'DELANTERO',           1,  31,   1, 0.24,  2,  5],
            ['OP-0439', 'CERRAR',                'LATERAL',             null,                  2, 117,   2, 0.88,  2,  5],
            ['OP-0440', 'CERRAR',                'ENTREPIERNA',         null,                  2, 157,   1, 0.53,  2,  5],
            ['OP-0441', 'PRENDER',               'INSTRUCCION LAVADO',  null,                  2,   2,   1, 0.18,  2,  2],
            ['OP-0445', 'FILETEAR',              'RUEDO',               null,                  2,  77,   2, 0.40,  2,  3],
            ['OP-0448', 'REVISAR',               'PRENDA COMPLETA',     null,                  3,  null, 1, 0.69,  2,  1],
            ['OP-0449', 'DOBLAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.55,  2,  1],
            ['OP-0450', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07,  2,  1],
            ['OP-0451', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07,  2,  1],
            ['OP-0452', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.13,  2,  1],
            ['OP-0453', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09,  2,  1],

            // ── 301630 (id=12) ──────────────────────────────────────────────
            ['OP-0454', 'CERRAR',                'BOLSILLO',            'DELANTERO',           1,  32,   2, 0.36, 12,  3],
            ['OP-0455', 'PRENDER',               'BOLSILLO',            'DELANTERO',           1,  15,   2, 0.37, 12,  2],
            ['OP-0456', 'PESPUNTAR',             'BOLSILLO',            'DELANTERO',           1,  15,   2, 0.35, 12,  2],
            ['OP-0457', 'FIJAR',                 'BOLSILLO',            'DELANTERO',           1,  15,   2, 0.47, 12,  2],
            ['OP-0458', 'CERRAR',                'TIRO',                'DELANTERO',           1,  22,   1, 0.24, 12,  3],
            ['OP-0459', 'CERRAR',                'TIRO',                'TRASERO',             1,  32,   1, 0.24, 12,  3],
            ['OP-0460', 'MEDIR Y CORTAR',        'ELASTICO',            'PRETINA',             1,  85,   1, 0.23, 12,  1],
            ['OP-0461', 'UNIR',                  'ELASTICO',            null,                  1,  85,   1, 0.09, 12,  2],
            ['OP-0462', 'UNIR',                  'PRETINA',             null,                  1,  85,   2, 0.26, 12,  2],
            ['OP-0463', 'CERRAR',                'PRETINA',             'CONTORNO',            1,  85,   1, 0.68, 12, 15],
            ['OP-0464', 'CERRAR',                'LATERAL',             null,                  2, 102,   2, 0.75, 12,  3],
            ['OP-0469', 'PRENDER',               'PRETINA',             null,                  2,  85,   1, 0.82, 12,  4],
            ['OP-0470', 'REVISAR',               'PRENDA COMPLETA',     null,                  3,  null, 1, 0.50, 12,  1],
            ['OP-0471', 'INSERTAR',              'CORDON',              null,                  3,  null, 1, 0.45, 12,  1],
            ['OP-0472', 'PLANCHAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.70, 12, 14],
            ['OP-0473', 'DOBLAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.55, 12,  1],
            ['OP-0474', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07, 12,  1],
            ['OP-0475', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07, 12,  1],
            ['OP-0476', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.13, 12,  1],
            ['OP-0477', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09, 12,  1],

            // ── 301662 (id=4) ───────────────────────────────────────────────
            ['OP-0478', 'OJALAR',                'CAPOTA',              null,                  1,   5,   2, 0.20,  4,  9],
            ['OP-0479', 'UNIR',                  'CAPOTA',              null,                  1,  62,   1, 0.29,  4,  3],
            ['OP-0481', 'UNIR Y VOLTEAR',        'PUÃO',                null,                  1,  27,   2, 0.46,  4,  2],
            ['OP-0482', 'UNIR Y VOLTEAR',        'FAJON',               null,                  1, 116,   1, 0.36,  4,  2],
            ['OP-0488', 'UNIR',                  'HOMBROS',             null,                  2,  18,   2, 0.42,  4,  3],
            ['OP-0489', 'PRENDER',               'CAPOTA',              null,                  2,  62,   1, 0.76,  4,  3],
            ['OP-0490', 'PRENDER',               'MANGA LARGA',         null,                  2,  58,   2, 0.58,  4,  5],
            ['OP-0491', 'CERRAR',                'LATERAL',             null,                  2,  90,   2, 0.83,  4,  5],
            ['OP-0492', 'PRENDER',               'PUÃO',                null,                  2,  27,   2, 0.83,  4,  5],
            ['OP-0493', 'PRENDER',               'FAJON',               null,                  2, 116,   1, 0.83,  4,  5],
            ['OP-0494', 'REVISAR',               'PRENDA COMPLETA',     null,                  3,  null, 1, 0.64,  4,  1],
            ['OP-0495', 'INSERTAR',              'CORDON',              null,                  3,  null, 1, 0.45,  4,  1],
            ['OP-0496', 'PLANCHAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.70,  4, 14],
            ['OP-0497', 'DOBLAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.64,  4,  1],
            ['OP-0498', 'COLOCAR',               'STICKER',             null,                  3,  null, 1, 0.07,  4,  1],
            ['OP-0499', 'TIQUETEAR',             'PRENDA COMPLETA',     null,                  3,  null, 1, 0.07,  4,  1],
            ['OP-0500', 'EMBOLSAR',              'PRENDA COMPLETA',     null,                  3,  null, 1, 0.20,  4,  1],
            ['OP-0501', 'UBICAR',                'PRENDA COMPLETA',     null,                  3,  null, 1, 0.09,  4,  1],
        ];

        foreach ($operations as [$codigo, $action, $garmentPart, $anexo, $sectionId, $measure, $quantity, $samBase, $sheetRef, $machineRef]) {
            Operation::withoutGlobalScopes()->updateOrCreate(
                ['codigo' => $codigo],
                [
                    'action'                     => $action,
                    'garment_part'               => $garmentPart,
                    'anexo'                      => $anexo,
                    'section_id'                 => $sectionId,
                    'measure'                    => $measure,
                    'quantity'                   => $quantity,
                    'sam_base'                   => $samBase,
                    'factor_operario'            => 100.0,
                    'product_sheet_id_reference' => $sheetRef,
                    'machine_id_reference'       => $machineRef,
                    'is_active'                  => true,
                ]
            );
        }
    }
}
