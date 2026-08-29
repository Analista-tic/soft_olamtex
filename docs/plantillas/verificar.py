# -*- coding: utf-8 -*-
"""Verifica los .xlsx generados contra las constantes reales del codigo PHP."""
import os
import re
import sys
from openpyxl import load_workbook

OUT = os.path.dirname(os.path.abspath(__file__))          # docs/plantillas/
PROJ = os.path.abspath(os.path.join(OUT, '..', '..'))     # raiz del repo
fails = []


def check(cond, msg):
    print(('  OK   ' if cond else '  FAIL ') + msg)
    if not cond:
        fails.append(msg)


def php(path):
    with open(os.path.join(PROJ, path), encoding='utf-8') as f:
        return f.read()


# ── 1. Extraer encabezados REALES del codigo PHP ──────────────
src_item = php('app/Services/MasterData/ItemExcelService.php')
blk = src_item.split('public function importTemplateHeaders(): array', 1)[1]
blk = blk.split('return [', 1)[1].split('];', 1)[0]
ITEM_SRC = re.findall(r"'((?:[^'\\]|\\.)*)'", blk)

src_roll = php('app/Http/Controllers/Textil/FabricRollController.php')
blk2 = src_roll.split('ROLL_IMPORT_EXPORT_HEADERS = [', 1)[1].split('];', 1)[0]
ROLL_SRC = re.findall(r"'((?:[^'\\]|\\.)*)'", blk2)

print(f"\nEncabezados leidos del codigo: items={len(ITEM_SRC)} rollos={len(ROLL_SRC)}")

# ── 2. Plantilla de items ─────────────────────────────────────
print('\n[01_Plantilla_Items.xlsx]')
wb = load_workbook(os.path.join(OUT, '01_Plantilla_Items.xlsx'))
ws = wb['items']
hdr = [c.value for c in ws[1]]
check(wb.active.title == 'items',
      f"la hoja ACTIVA es 'items' (el importador usa getActiveSheet) -> es '{wb.active.title}'")
check(hdr == ITEM_SRC, 'encabezados identicos a ItemExcelService::importTemplateHeaders()')
if hdr != ITEM_SRC:
    print('     esperado:', ITEM_SRC)
    print('     obtenido:', hdr)
check(ws.max_row == 1, f'hoja items sin filas de datos precargadas (max_row={ws.max_row})')
check('LISTAS' in wb.sheetnames and 'SUBGRUPOS POR GRUPO' in wb.sheetnames
      and 'COLORES DISPONIBLES' in wb.sheetnames and 'INSTRUCCIONES' in wb.sheetnames,
      'hojas de apoyo presentes')
print('     hojas:', wb.sheetnames)

# ── 3. Plantilla de rollos ────────────────────────────────────
print('\n[02_Plantilla_Rollos.xlsx]')
wb2 = load_workbook(os.path.join(OUT, '02_Plantilla_Rollos.xlsx'))
check('rollos' in wb2.sheetnames, "existe hoja llamada 'rollos' (getSheetByName)")
ws2 = wb2['rollos']
hdr2 = [c.value for c in ws2[1]]
check(hdr2 == ROLL_SRC, 'encabezados identicos a ROLL_IMPORT_EXPORT_HEADERS')
if hdr2 != ROLL_SRC:
    print('     esperado:', ROLL_SRC)
    print('     obtenido:', hdr2)
check(len(hdr2) == 15, f'exactamente 15 columnas (el import lo exige) -> {len(hdr2)}')
check(ws2.max_column == 15, f'max_column == 15 (nada escrito fuera de A:O) -> {ws2.max_column}')
check(ws2.max_row == 1, f'hoja rollos sin filas precargadas (max_row={ws2.max_row})')
print('     hojas:', wb2.sheetnames)

# ── 4. Catalogos base ─────────────────────────────────────────
print('\n[03_Catalogos_Base.xlsx]')
wb3 = load_workbook(os.path.join(OUT, '03_Catalogos_Base.xlsx'))
esperadas = {'BODEGAS', 'UBICACIONES', 'CONTACTOS', 'COLORES NUEVOS',
             'SUBGRUPOS NUEVOS', 'INSTRUCCIONES'}
check(esperadas.issubset(set(wb3.sheetnames)), 'todas las hojas esperadas presentes')
print('     hojas:', wb3.sheetnames)

# ── 5. Conteo real de colores en el seeder ────────────────────
src_col = php('database/seeders/ColorsSeeder.php')
cat = src_col.split('private function catalog(): array', 1)[1]
total_entries = len(re.findall(r"\['code'\s*=>", cat))
parsed = len(re.findall(
    r"\['code'\s*=>\s*'([^']*)'\s*,\s*'name'\s*=>\s*'((?:[^'\\]|\\.)*)'\s*,\s*'hexadecimal'\s*=>\s*(null|'[^']*')",
    cat))
print(f"\n[ColorsSeeder] entradas totales={total_entries}  capturadas por el parser={parsed}")
check(total_entries == parsed,
      'el parser capturo TODOS los colores del seeder (sin perder ninguno)')
if total_entries != parsed:
    got = {n for _, n, _ in re.findall(
        r"\['code'\s*=>\s*'([^']*)'\s*,\s*'name'\s*=>\s*'((?:[^'\\]|\\.)*)'\s*,\s*'hexadecimal'\s*=>\s*(null|'[^']*')", cat)}
    for line in cat.splitlines():
        if "['code'" in line and not re.search(
            r"\['code'\s*=>\s*'([^']*)'\s*,\s*'name'\s*=>\s*'((?:[^'\\]|\\.)*)'\s*,\s*'hexadecimal'\s*=>\s*(null|'[^']*')", line):
            print('     NO CAPTURADA ->', line.strip())

print('\n' + ('TODO CORRECTO' if not fails else f'{len(fails)} PROBLEMA(S): ' + ' | '.join(fails)))
sys.exit(1 if fails else 0)
