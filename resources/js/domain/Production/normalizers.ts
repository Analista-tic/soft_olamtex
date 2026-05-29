import type { AdditionalInfo, CuttingPiece } from './types';

export function resolveDrawingUrl(raw: string | null | undefined): string | null {
    const path = typeof raw === 'string' ? raw.trim() : '';
    if (!path) return null;
    if (/^https?:\/\//i.test(path) || /^(data:|blob:)/i.test(path)) return path;
    if (path.startsWith('/storage-file/')) return path;
    if (path.startsWith('/')) return `/storage-file/${path.replace(/^\/+(?:storage\/)?/, '')}`;
    return `/storage-file/${path.replace(/^\/?storage\//, '')}`;
}

export function buildSizeCurvesFromCurves(
    curves: Record<string, any> | null | undefined,
): Array<Record<string, any>> {
    const principal = (curves?.principal ?? {}) as Record<string, any>;
    const liquidacion = (curves?.liquidacion ?? {}) as Record<string, any>;
    const espiga = (curves?.espiga ?? {}) as Record<string, any>;
    const sizes = ['S', 'M', 'L', 'XL'];
    return sizes
        .map((size) => ({
            size,
            trazoPrincipal: Number(principal[size] ?? 0),
            liquidacion: Number(liquidacion[size] ?? 0),
            espiga: Number(espiga[size] ?? 0),
            totalUnidades: 0,
        }))
        .filter((row) => row.trazoPrincipal > 0 || row.liquidacion > 0 || row.espiga > 0);
}

export function deriveColorCapas(lines: Array<Record<string, any>>): Array<Record<string, any>> {
    const byColor = lines.reduce<Record<string, any>>((acc, line) => {
        const colorName = String(line.color ?? '').trim();
        if (!colorName) return acc;
        if (!acc[colorName]) {
            acc[colorName] = {
                color: colorName,
                colorCode: String(line.color_code ?? line.colorCode ?? '').trim() || null,
                layersFabric: 0,
                layersLiq: 0,
                layersHerringbone: 0,
            };
        }
        acc[colorName].layersFabric += Number(line.layers_fabric ?? line.layersFabric ?? 0);
        acc[colorName].layersLiq += Number(line.layers_liquidation ?? line.layersLiq ?? 0);
        acc[colorName].layersHerringbone += Number(line.layers_herringbone ?? line.layersHerringbone ?? 0);
        return acc;
    }, {});
    return Object.values(byColor);
}

export function normalizeAdditionalInfoForSummary(raw: unknown): AdditionalInfo {
    const data = (raw ?? {}) as Record<string, any>;
    const cuttingRaw = (data.cutting ?? {}) as Record<string, any>;

    const normalizedSizes = Array.isArray(cuttingRaw.sizes)
        ? cuttingRaw.sizes.map((sizeRow: Record<string, any>) => ({
              size: String(sizeRow.size ?? ''),
              trazoPrincipal: Number(sizeRow.trazoPrincipal ?? 0),
              liquidacion: Number(sizeRow.liquidacion ?? 0),
              espiga: Number(sizeRow.espiga ?? 0),
              totalUnidades: Number(sizeRow.totalUnidades ?? sizeRow.units ?? 0),
              units: Number(sizeRow.units ?? sizeRow.totalUnidades ?? 0),
              curve: sizeRow.curve ?? null,
          }))
        : [];

    const normalizedSizeCurves =
        Array.isArray(cuttingRaw.sizeCurves) && cuttingRaw.sizeCurves.length > 0
            ? cuttingRaw.sizeCurves.map((sizeRow: Record<string, any>) => ({
                  size: String(sizeRow.size ?? ''),
                  trazoPrincipal: Number(sizeRow.trazoPrincipal ?? 0),
                  liquidacion: Number(sizeRow.liquidacion ?? 0),
                  espiga: Number(sizeRow.espiga ?? 0),
                  totalUnidades: Number(sizeRow.totalUnidades ?? sizeRow.units ?? 0),
              }))
            : normalizedSizes.map((sizeRow: Record<string, any>) => ({
                  size: String(sizeRow.size ?? ''),
                  trazoPrincipal: Number(sizeRow.trazoPrincipal ?? 0),
                  liquidacion: Number(sizeRow.liquidacion ?? 0),
                  espiga: Number(sizeRow.espiga ?? 0),
                  totalUnidades: Number(sizeRow.totalUnidades ?? sizeRow.units ?? 0),
              }));

    const cutFabricLines = Array.isArray(data.cutFabricLines)
        ? data.cutFabricLines
        : Array.isArray(cuttingRaw.cutFabricLines)
          ? cuttingRaw.cutFabricLines
          : [];

    const colorCapasFromPayload = Array.isArray(cuttingRaw.colorCapas) ? cuttingRaw.colorCapas : [];
    const colorCapasDerived = cutFabricLines.reduce<Record<string, any>>((acc, line: Record<string, any>) => {
        const colorName = String(line.color ?? '').trim();
        if (!colorName) return acc;
        if (!acc[colorName]) {
            acc[colorName] = {
                color: colorName,
                colorCode: String(line.color_code ?? line.colorCode ?? '').trim() || null,
                layersFabric: 0,
                layersLiq: 0,
                layersHerringbone: 0,
            };
        }
        acc[colorName].layersFabric += Number(line.layers_fabric ?? line.layersFabric ?? 0);
        acc[colorName].layersLiq += Number(line.layers_liquidation ?? line.layersLiq ?? 0);
        acc[colorName].layersHerringbone += Number(line.layers_herringbone ?? line.layersHerringbone ?? 0);
        return acc;
    }, {});
    const normalizedColorCapas = colorCapasFromPayload.length > 0 ? colorCapasFromPayload : Object.values(colorCapasDerived);

    const normalizedCuttingPieces: CuttingPiece[] = Array.isArray(cuttingRaw.pieces)
        ? cuttingRaw.pieces.map((piece: Record<string, any>) => {
              const stableId = String(piece.id ?? piece.uid ?? '').trim();
              return {
                  id: stableId,
                  uid: stableId || undefined,
                  name: String(piece.name ?? ''),
                  type: piece.type === 'SECUNDARIO' ? 'SECUNDARIO' : ('PRINCIPAL' as const),
                  fabricUid: piece.fabricUid ? String(piece.fabricUid) : null,
                  side: (['IZQUIERDA', 'DERECHA', 'AMBOS', 'NO_APLICA'] as const).includes(String(piece.side) as any)
                      ? (piece.side as CuttingPiece['side'])
                      : ('NO_APLICA' as const),
                  grain: (['90', '180', 'SESGO'] as const).includes(String(piece.grain) as any)
                      ? (piece.grain as CuttingPiece['grain'])
                      : ('90' as const),
                  quantity: piece.quantity != null ? Number(piece.quantity) : undefined,
                  notes: piece.notes ?? null,
              };
          })
        : [];

    return {
        cutting: {
            params: {
                cutCostCop:
                    cuttingRaw?.params?.cutCostCop !== null && cuttingRaw?.params?.cutCostCop !== undefined
                        ? Number(cuttingRaw.params.cutCostCop)
                        : null,
            },
            sizes: normalizedSizes,
            pieces: normalizedCuttingPieces,
            generalNotes: cuttingRaw.generalNotes ?? null,
            sizeCurves: normalizedSizeCurves,
            colorCapas: normalizedColorCapas,
            cutFabricLines,
            espigaLength: cuttingRaw.espigaLength ?? null,
            espigaCurve: cuttingRaw.espigaCurve ?? null,
            liqLength: cuttingRaw.liqLength ?? null,
            liqCurve: cuttingRaw.liqCurve ?? null,
        } as AdditionalInfo['cutting'],
        stamping: {
            designNotes: data?.stamping?.designNotes ?? null,
            attachments: Array.isArray(data?.stamping?.attachments) ? data.stamping.attachments : [],
            drawingAttachments: Array.isArray(data?.stamping?.drawingAttachments) ? data.stamping.drawingAttachments : [],
            colorCodes: Array.isArray(data?.stamping?.colorCodes) ? data.stamping.colorCodes : null,
        },
        laundry: {
            type: data?.laundry?.type ?? null,
            instructions: data?.laundry?.instructions ?? null,
            attachments: Array.isArray(data?.laundry?.attachments) ? data.laundry.attachments : [],
        },
        cutFabricLines,
    } as AdditionalInfo;
}

export function mergeCutConsumptionIntoAdditionalInfo(
    base: AdditionalInfo,
    cutConsumption: Record<string, any> | null,
): AdditionalInfo {
    if (!cutConsumption) return base;

    const lines = Array.isArray(cutConsumption.lines) ? cutConsumption.lines : [];
    const merged: AdditionalInfo = {
        ...base,
        cutting: { ...base.cutting } as AdditionalInfo['cutting'],
    };

    if (lines.length > 0) {
        (merged.cutting as any).cutFabricLines = lines;
        (merged as any).cutFabricLines = lines;
    }

    const existingSizeCurves = Array.isArray((merged.cutting as any).sizeCurves)
        ? (merged.cutting as any).sizeCurves
        : [];
    if (existingSizeCurves.length === 0) {
        const curvesFromConsumption = buildSizeCurvesFromCurves(
            (cutConsumption.curves as Record<string, any> | null) ??
                (cutConsumption.sheet_curves as Record<string, any> | null),
        );
        if (curvesFromConsumption.length > 0) {
            (merged.cutting as any).sizeCurves = curvesFromConsumption;
        }
    }

    const existingColorCapas = Array.isArray((merged.cutting as any).colorCapas)
        ? (merged.cutting as any).colorCapas
        : [];
    if (existingColorCapas.length === 0 && lines.length > 0) {
        (merged.cutting as any).colorCapas = deriveColorCapas(lines);
    }

    if ((merged.cutting as any).espigaLength == null && cutConsumption.largo_espiga != null) {
        (merged.cutting as any).espigaLength = cutConsumption.largo_espiga;
    }

    return merged;
}
