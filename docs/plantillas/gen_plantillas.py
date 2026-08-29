# -*- coding: utf-8 -*-
"""
Genera las plantillas de carga inicial para Olamtex.
Los encabezados se toman VERBATIM del codigo fuente del sistema:
  - Items : app/Services/MasterData/ItemExcelService.php::importTemplateHeaders()
  - Rollos: app/Http/Controllers/Textil/FabricRollController.php::ROLL_IMPORT_EXPORT_HEADERS
Los valores validos se extraen de los seeders reales.
"""
import json
import re
import os
import sys

from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.datavalidation import DataValidation

OUT = os.path.dirname(os.path.abspath(__file__))          # docs/plantillas/
PROJ = os.path.abspath(os.path.join(OUT, '..', '..'))     # raiz del repo

# ─────────────────────────────────────────────────────────────
# Encabezados EXACTOS (copiados del codigo fuente)
# ─────────────────────────────────────────────────────────────
ITEM_HEADERS = [
    'TIPO DE ITEM', 'NOMBRE', 'GRUPO', 'SUBGRUPO', 'IMPUESTO (%)', 'BODEGA',
    'PRECIO', 'CANTIDAD', 'CANTIDAD MIN', 'CANTIDAD MAX', 'UNIDAD DE MEDIDA',
    'CODIGO DE BARRAS', 'REFERENCIA', 'DESCRIPCION', 'MOVIMIENTO NEGATIVO',
    'CAMPOS ADICIONALES', 'VALORES ADICIONALES', 'COLOR', 'ESTADO',
]

ROLL_HEADERS = [
    'N° ROLLO', 'UBICACIÓN', 'BASE TELA', 'TELA', 'COLOR', 'CÓDIGO COLOR',
    'ANCHO (m)', 'PRECIO/M', 'CANTIDAD (m)', 'SALIDA PARCIAL',
    'DISPONIBILIDAD REAL', 'PROVEEDOR', 'N° PEDIDO', 'FECHA INGRESO',
    'REFERENCIA ÍTEM',
]


def read(path):
    with open(os.path.join(PROJ, path), encoding='utf-8') as f:
        return f.read()


# ─────────────────────────────────────────────────────────────
# Extraccion de valores validos desde los seeders
# ─────────────────────────────────────────────────────────────
def parse_categories():
    src = read('database/seeders/CategorySeeder.php')
    gblock = src.split('$groups = [', 1)[1].split('];', 1)[0]
    groups = re.findall(r"'name'\s*=>\s*'((?:[^'\\]|\\.)*)'", gblock)
    groups = [g.replace("\\'", "'") for g in groups]

    sblock = src.split('$subGroups = [', 1)[1].split("\n        ];", 1)[0]
    subs = re.findall(
        r"'name'\s*=>\s*'((?:[^'\\]|\\.)*)'\s*,\s*'category_group_id'\s*=>\s*\$groupIds\['((?:[^'\\]|\\.)*)'\]",
        sblock)
    subs = [(n.replace("\\'", "'"), g.replace("\\'", "'")) for n, g in subs]
    return groups, subs


def parse_colors():
    src = read('database/seeders/ColorsSeeder.php')
    block = src.split('return [', 1)[1]
    rows = re.findall(
        r"\['code'\s*=>\s*'([^']*)'\s*,\s*'name'\s*=>\s*'((?:[^'\\]|\\.)*)'\s*,\s*'hexadecimal'\s*=>\s*(null|'[^']*')",
        block)
    return [(c, n.replace("\\'", "'"), (h.strip("'") if h != 'null' else ''))
            for c, n, h in rows]


def parse_warehouses():
    src = read('database/seeders/WarehousesSeeder.php')
    block = src.split('$warehouses = [', 1)[1].split('];', 1)[0]
    return re.findall(r"'name'\s*=>\s*'([^']*)'\s*,\s*'observation'\s*=>\s*'([^']*)'", block)


def parse_taxes():
    src = read('database/seeders/TaxesSeeder.php')
    block = src.split('$taxes = [', 1)[1].split('];', 1)[0]
    return re.findall(r"'name'\s*=>\s*'([^']*)'\s*,\s*'percentage'\s*=>\s*([0-9.]+)", block)


def parse_units():
    with open(os.path.join(PROJ, 'resources/js/data/unitMeasures.json'), encoding='utf-8') as f:
        data = json.load(f)
    out = []
    for grp in data['unitMeasureGroups']:
        for o in grp['options']:
            out.append(o['name'])
    return out


GROUPS, SUBS = parse_categories()
COLORS = parse_colors()
WAREHOUSES = parse_warehouses()
TAXES = parse_taxes()
UNITS = parse_units()

print(f"Grupos: {len(GROUPS)} | Subgrupos: {len(SUBS)} | Colores: {len(COLORS)} | "
      f"Bodegas: {len(WAREHOUSES)} | Impuestos: {len(TAXES)} | Unidades: {len(UNITS)}")

# ─────────────────────────────────────────────────────────────
# Estilos
# ─────────────────────────────────────────────────────────────
HDR_FILL = PatternFill('solid', fgColor='1F2430')
HDR_FONT = Font(bold=True, color='FFFFFF', size=10)
REQ_FILL = PatternFill('solid', fgColor='4A5568')
TITLE_FONT = Font(bold=True, size=13)
SUB_FONT = Font(bold=True, size=10, color='1F2430')
THIN = Side(style='thin', color='D0D0D0')
BOX = Border(left=THIN, right=THIN, top=THIN, bottom=THIN)

# Columnas obligatorias -> se pintan distinto para que se vean de una
ITEM_REQUIRED = {'SUBGRUPO', 'REFERENCIA'}
ROLL_REQUIRED = {'N° ROLLO', 'CANTIDAD (m)', 'FECHA INGRESO', 'REFERENCIA ÍTEM'}


def style_header(ws, headers, required):
    for i, h in enumerate(headers, start=1):
        c = ws.cell(row=1, column=i, value=h)
        c.font = HDR_FONT
        c.fill = REQ_FILL if h in required else HDR_FILL
        c.alignment = Alignment(horizontal='center', vertical='center', wrap_text=True)
        c.border = BOX
        ws.column_dimensions[get_column_letter(i)].width = max(14, min(len(h) + 6, 28))
    ws.row_dimensions[1].height = 32
    ws.freeze_panes = 'A2'


def write_instructions(ws, title, blocks):
    ws.column_dimensions['A'].width = 34
    ws.column_dimensions['B'].width = 92
    r = 1
    ws.cell(row=r, column=1, value=title).font = TITLE_FONT
    r += 2
    for section, lines in blocks:
        ws.cell(row=r, column=1, value=section).font = SUB_FONT
        r += 1
        for a, b in lines:
            ws.cell(row=r, column=1, value=a).font = Font(bold=True, size=9)
            ws.cell(row=r, column=1).alignment = Alignment(vertical='top', wrap_text=True)
            cb = ws.cell(row=r, column=2, value=b)
            cb.alignment = Alignment(vertical='top', wrap_text=True)
            r += 1
        r += 1


def add_list_sheet(wb, name, columns):
    """columns: list of (header, [values])  -> devuelve dict header -> rango absoluto"""
    ws = wb.create_sheet(name)
    ranges = {}
    for i, (h, vals) in enumerate(columns, start=1):
        col = get_column_letter(i)
        ws.cell(row=1, column=i, value=h).font = Font(bold=True, size=10)
        for j, v in enumerate(vals, start=2):
            ws.cell(row=j, column=i, value=v)
        ws.column_dimensions[col].width = max(16, min(len(h) + 8, 34))
        if vals:
            ranges[h] = f"'{name}'!${col}$2:${col}${len(vals) + 1}"
    ws.freeze_panes = 'A2'
    return ws, ranges


def dv(ws, rng_formula, target_range, prompt):
    v = DataValidation(type='list', formula1=rng_formula, allow_blank=True,
                       showDropDown=False, errorStyle='warning')
    v.error = prompt
    v.errorTitle = 'Valor no reconocido'
    ws.add_data_validation(v)
    v.add(target_range)


# ═════════════════════════════════════════════════════════════
# 1) PLANTILLA DE ITEMS
# ═════════════════════════════════════════════════════════════
wb = Workbook()
ws_items = wb.active
ws_items.title = 'items'          # el importador usa la hoja ACTIVA
style_header(ws_items, ITEM_HEADERS, ITEM_REQUIRED)

sub_names = sorted({n for n, _ in SUBS})
color_names = [n for _, n, _ in COLORS]
wh_names = [n for n, _ in WAREHOUSES]
tax_pcts = sorted({str(int(float(p))) for _, p in TAXES})

_, R = add_list_sheet(wb, 'LISTAS', [
    ('SUBGRUPOS', sub_names),
    ('COLORES', color_names),
    ('BODEGAS', wh_names),
    ('UNIDADES', UNITS),
    ('IMPUESTOS', tax_pcts),
    ('TIPO DE ITEM', ['Producto', 'Servicio']),
    ('SI/NO', ['SI', 'NO']),
    ('ESTADO', ['Activo', 'Inactivo']),
])

LAST = 2000
col_of = {h: get_column_letter(i) for i, h in enumerate(ITEM_HEADERS, start=1)}
dv(ws_items, R['SUBGRUPOS'], f"{col_of['SUBGRUPO']}2:{col_of['SUBGRUPO']}{LAST}",
   'El SUBGRUPO debe existir en el sistema. Si no esta en la lista, avisanos para crearlo antes de cargar.')
dv(ws_items, R['COLORES'], f"{col_of['COLOR']}2:{col_of['COLOR']}{LAST}",
   'Ese color no existe en el catalogo. Si lo dejas asi, el item se cargara SIN color y el sistema no avisara.')
dv(ws_items, R['BODEGAS'], f"{col_of['BODEGA']}2:{col_of['BODEGA']}{LAST}",
   'Esa bodega no existe. El item se cargaria sin bodega.')
dv(ws_items, R['UNIDADES'], f"{col_of['UNIDAD DE MEDIDA']}2:{col_of['UNIDAD DE MEDIDA']}{LAST}",
   'Unidad no reconocida por el sistema.')
dv(ws_items, R['IMPUESTOS'], f"{col_of['IMPUESTO (%)']}2:{col_of['IMPUESTO (%)']}{LAST}",
   'Si escribes un porcentaje que no existe, el sistema CREARA un impuesto nuevo.')
dv(ws_items, R['TIPO DE ITEM'], f"{col_of['TIPO DE ITEM']}2:{col_of['TIPO DE ITEM']}{LAST}", 'Producto o Servicio.')
dv(ws_items, R['SI/NO'], f"{col_of['MOVIMIENTO NEGATIVO']}2:{col_of['MOVIMIENTO NEGATIVO']}{LAST}", 'Escribe SI o NO.')
dv(ws_items, R['ESTADO'], f"{col_of['ESTADO']}2:{col_of['ESTADO']}{LAST}", 'Activo o Inactivo.')

ws_ref = wb.create_sheet('SUBGRUPOS POR GRUPO')
ws_ref.cell(row=1, column=1, value='GRUPO').font = Font(bold=True)
ws_ref.cell(row=1, column=2, value='SUBGRUPO').font = Font(bold=True)
ws_ref.column_dimensions['A'].width = 26
ws_ref.column_dimensions['B'].width = 34
for j, (n, g) in enumerate(sorted(SUBS, key=lambda x: (x[1], x[0])), start=2):
    ws_ref.cell(row=j, column=1, value=g)
    ws_ref.cell(row=j, column=2, value=n)
ws_ref.freeze_panes = 'A2'

ws_col = wb.create_sheet('COLORES DISPONIBLES')
for i, h in enumerate(['CODIGO', 'NOMBRE', 'TONO'], start=1):
    ws_col.cell(row=1, column=i, value=h).font = Font(bold=True)
ws_col.column_dimensions['A'].width = 12
ws_col.column_dimensions['B'].width = 30
ws_col.column_dimensions['C'].width = 14
for j, (c, n, h) in enumerate(COLORS, start=2):
    ws_col.cell(row=j, column=1, value=c)
    ws_col.cell(row=j, column=2, value=n)
    ws_col.cell(row=j, column=3, value=h)
ws_col.freeze_panes = 'A2'

write_instructions(wb.create_sheet('INSTRUCCIONES'), 'PLANTILLA DE ITEMS - COMO LLENARLA', [
    ('REGLAS GENERALES', [
        ('Hoja a llenar', 'Escribe unicamente en la hoja "items". No cambies el nombre de la hoja ni el texto de la fila 1.'),
        ('No borres columnas', 'El sistema busca las columnas por su nombre. Puedes dejar vacia una columna, pero no la elimines.'),
        ('Una fila = un item', 'No dejes filas vacias intermedias.'),
        ('Listas desplegables', 'Las columnas SUBGRUPO, COLOR, BODEGA, UNIDAD, IMPUESTO, TIPO y ESTADO tienen lista desplegable. Usala siempre.'),
    ]),
    ('COLUMNAS OBLIGATORIAS', [
        ('SUBGRUPO', 'Obligatorio. Debe existir en el sistema. Si no existe, esa fila se rechaza completa. Consulta la hoja "SUBGRUPOS POR GRUPO".'),
        ('REFERENCIA', 'El sistema no la exige, pero SIN ELLA NO SE PUEDEN CARGAR ROLLOS de esa tela. Para telas trátala como obligatoria y que sea unica.'),
        ('PRECIO', 'Obligatorio SOLO si pones un valor mayor a cero en CANTIDAD.'),
    ]),
    ('COLUMNAS CON TRAMPA', [
        ('COLOR', 'Si el nombre no coincide EXACTO con el catalogo, el item se carga SIN color y el sistema NO avisa. Usa siempre el desplegable.'),
        ('IMPUESTO (%)', 'Escribe solo el numero (19, 5, 0). Si pones un porcentaje que no existe, el sistema CREA un impuesto nuevo automaticamente.'),
        ('BODEGA', 'Si la bodega no existe, el item se carga SIN bodega y se reporta un error de esa fila.'),
        ('CODIGO DE BARRAS', 'No se puede repetir entre items. Un duplicado rechaza la fila.'),
        ('NOMBRE', 'Puedes dejarla vacia: el sistema arma el nombre con el subgrupo, los campos adicionales y el color.'),
        ('GRUPO', 'Es solo ayuda visual. El sistema toma el grupo desde el subgrupo.'),
    ]),
    ('CAMPOS ADICIONALES', [
        ('Como se llenan', 'Son DOS columnas que trabajan juntas, separadas por punto y coma, con la MISMA cantidad de elementos y en el MISMO orden.'),
        ('Ejemplo', 'CAMPOS ADICIONALES: BASE TELA;MARCA     |     VALORES ADICIONALES: RIB;Mocca'),
        ('Importante para telas', 'Aqui es donde se define la BASE TELA que despues valida la plantilla de rollos. Si la escribes aqui, debe coincidir con la que pongas en rollos.'),
        ('Campos reconocidos', 'BASE TELA, BASE HILAZA, MARCA, MUNDO, TIPO PUNTERA, TALLA, MEDIDA, CAMPO ADICIONAL.'),
    ]),
    ('EJEMPLO DE UNA FILA DE TELA', [
        ('TIPO DE ITEM', 'Producto'),
        ('SUBGRUPO', 'TELA'),
        ('REFERENCIA', 'TEL-RIB-001   (debe ser unica)'),
        ('UNIDAD DE MEDIDA', 'Metro'),
        ('COLOR', 'NEGRO   (elegido del desplegable)'),
        ('CAMPOS ADICIONALES', 'BASE TELA'),
        ('VALORES ADICIONALES', 'RIB'),
    ]),
])

p1 = os.path.join(OUT, '01_Plantilla_Items.xlsx')
wb.save(p1)
print('OK ->', p1)

# ═════════════════════════════════════════════════════════════
# 2) PLANTILLA DE ROLLOS  (hoja "rollos", EXACTAMENTE 15 columnas)
# ═════════════════════════════════════════════════════════════
wb2 = Workbook()
ws_r = wb2.active
ws_r.title = 'rollos'
style_header(ws_r, ROLL_HEADERS, ROLL_REQUIRED)

_, R2 = add_list_sheet(wb2, 'LISTAS', [('COLORES', color_names)])
rcol = {h: get_column_letter(i) for i, h in enumerate(ROLL_HEADERS, start=1)}
dv(ws_r, R2['COLORES'], f"{rcol['COLOR']}2:{rcol['COLOR']}{LAST}", 'Color no reconocido en el catalogo.')

write_instructions(wb2.create_sheet('INSTRUCCIONES'), 'PLANTILLA DE ROLLOS DE TELA - COMO LLENARLA', [
    ('ANTES DE EMPEZAR', [
        ('Orden obligatorio', 'Esta plantilla SOLO se puede cargar DESPUES de que los items (telas) ya esten en el sistema.'),
        ('Hoja a llenar', 'Escribe unicamente en la hoja "rollos". No cambies el nombre de la hoja.'),
        ('NO agregues ni quites columnas', 'El sistema exige EXACTAMENTE estas 15 columnas, en este mismo orden y con este mismo texto. Si cambia una, se rechaza el archivo completo.'),
    ]),
    ('COLUMNAS OBLIGATORIAS', [
        ('N° ROLLO', 'Unico. Si el numero ya existe en el sistema, esa fila se salta sin cargarse.'),
        ('REFERENCIA ITEM', 'La referencia de la tela tal cual quedo en el catalogo de items. Si no existe, la fila se rechaza.'),
        ('CANTIDAD (m)', 'Metros del rollo. Debe ser mayor a cero.'),
        ('FECHA INGRESO', 'Fecha valida. Si no se puede leer, la fila se rechaza.'),
    ]),
    ('COLUMNAS CON TRAMPA', [
        ('BASE TELA', 'Si la llenas, debe coincidir con la BASE TELA de ese item. Si no coincide, la fila se rechaza. Si tienes dudas, DEJALA VACIA.'),
        ('DISPONIBILIDAD REAL', 'Metros que quedan hoy. No puede ser mayor que CANTIDAD (m). Si la dejas vacia, se asume el rollo completo.'),
        ('SALIDA PARCIAL', 'No puede ser mayor que CANTIDAD (m).'),
        ('PROVEEDOR y N° PEDIDO', 'NO PIERDAS TIEMPO LLENANDOLAS. En esta carga inicial el sistema no las guarda; se completan solas cuando el rollo entre desde una compra real.'),
    ]),
    ('EJEMPLO DE UNA FILA', [
        ('N° ROLLO', 'R-00125'),
        ('REFERENCIA ITEM', 'TEL-RIB-001'),
        ('CANTIDAD (m)', '48.5'),
        ('DISPONIBILIDAD REAL', '32   (si ya se consumieron 16.5 m)'),
        ('ANCHO (m)', '1.6'),
        ('PRECIO/M', '12500'),
        ('FECHA INGRESO', '15/03/2026'),
    ]),
])

p2 = os.path.join(OUT, '02_Plantilla_Rollos.xlsx')
wb2.save(p2)
print('OK ->', p2)

# ═════════════════════════════════════════════════════════════
# 3) CATALOGOS BASE (carga manual - formato definido por nosotros)
# ═════════════════════════════════════════════════════════════
wb3 = Workbook()

ws_b = wb3.active
ws_b.title = 'BODEGAS'
style_header(ws_b, ['NOMBRE DE LA BODEGA', 'OBSERVACION'], {'NOMBRE DE LA BODEGA', 'OBSERVACION'})
for j, (n, o) in enumerate(WAREHOUSES, start=2):
    ws_b.cell(row=j, column=1, value=n)
    ws_b.cell(row=j, column=2, value=o)
ws_b.cell(row=len(WAREHOUSES) + 3, column=1,
          value='^ Las de arriba YA estan en el sistema. Agrega debajo las que falten.').font = Font(italic=True, size=9)

ws_u = wb3.create_sheet('UBICACIONES')
style_header(ws_u, ['NOMBRE DE LA UBICACION', 'CODIGO', 'DESCRIPCION'], {'NOMBRE DE LA UBICACION'})

ws_c = wb3.create_sheet('CONTACTOS')
style_header(ws_c, [
    'TIPO', 'TIPO DOCUMENTO', 'NUMERO DOCUMENTO', 'NOMBRE COMPLETO',
    'TIPO DE PERSONA', 'CORREO ELECTRONICO', 'TELEFONO', 'DIRECCION',
    'MUNICIPIO', 'DEPARTAMENTO',
], {'TIPO', 'TIPO DOCUMENTO', 'NUMERO DOCUMENTO', 'NOMBRE COMPLETO', 'TIPO DE PERSONA', 'CORREO ELECTRONICO'})

_, R3 = add_list_sheet(wb3, 'LISTAS', [
    ('TIPO', ['Proveedor', 'Cliente', 'Cliente y proveedor']),
    ('TIPO DOCUMENTO', ['NIT', 'Cedula de ciudadania', 'Cedula de extranjeria', 'Pasaporte']),
    ('TIPO DE PERSONA', ['Persona juridica', 'Persona natural']),
])
dv(ws_c, R3['TIPO'], f'A2:A{LAST}', 'Elige una opcion de la lista.')
dv(ws_c, R3['TIPO DOCUMENTO'], f'B2:B{LAST}', 'Elige una opcion de la lista.')
dv(ws_c, R3['TIPO DE PERSONA'], f'E2:E{LAST}', 'Elige una opcion de la lista.')

ws_nc = wb3.create_sheet('COLORES NUEVOS')
style_header(ws_nc, ['NOMBRE DEL COLOR', 'TONO (opcional)'], {'NOMBRE DEL COLOR'})

ws_ns = wb3.create_sheet('SUBGRUPOS NUEVOS')
style_header(ws_ns, ['GRUPO AL QUE PERTENECE', 'NOMBRE DEL SUBGRUPO'],
             {'GRUPO AL QUE PERTENECE', 'NOMBRE DEL SUBGRUPO'})
_, R4 = add_list_sheet(wb3, 'LISTA GRUPOS', [('GRUPOS', GROUPS)])
dv(ws_ns, R4['GRUPOS'], f'A2:A{LAST}', 'Elige uno de los grupos existentes.')

write_instructions(wb3.create_sheet('INSTRUCCIONES'), 'CATALOGOS BASE - QUE LLENAR EN CADA HOJA', [
    ('PARA QUE SIRVE ESTE ARCHIVO', [
        ('Que es', 'Estos catalogos NO tienen carga automatica: los cargamos nosotros a mano. Por eso el formato aqui es libre y simple.'),
        ('Cuando', 'Este archivo se llena PRIMERO, antes que el de items y el de rollos.'),
    ]),
    ('HOJA POR HOJA', [
        ('BODEGAS', 'Ya trae las 2 que existen hoy. Agrega debajo las que falten, con nombre y una observacion corta.'),
        ('UBICACIONES', 'Solo si manejan ubicaciones dentro de la bodega (estante, zona, nivel). Hoy esta vacio en el sistema.'),
        ('CONTACTOS', 'Proveedores y clientes. Hoy esta vacio en el sistema.'),
        ('COLORES NUEVOS', 'SOLO los colores que NO esten ya en el sistema. Revisa antes la hoja "COLORES DISPONIBLES" del archivo de items (79 colores ya cargados).'),
        ('SUBGRUPOS NUEVOS', 'SOLO los que falten. Revisa antes la hoja "SUBGRUPOS POR GRUPO" del archivo de items.'),
    ]),
    ('OJO CON LOS CONTACTOS', [
        ('Correo obligatorio', 'El sistema EXIGE correo electronico para cada contacto. No admite dos contactos con el mismo correo.'),
        ('Documento unico', 'Tampoco admite dos contactos con el mismo numero de documento.'),
        ('Si falta correo', 'Avisanos antes de llenar la hoja. Es el punto que mas suele frenar esta carga.'),
    ]),
])

p3 = os.path.join(OUT, '03_Catalogos_Base.xlsx')
wb3.save(p3)
print('OK ->', p3)
