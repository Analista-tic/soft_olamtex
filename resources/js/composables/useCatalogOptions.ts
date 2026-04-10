/**
 * Composable: useCatalogOptions
 *
 * Encapsula toda la lógica de gestión de opciones de catálogo (secciones,
 * grupos, marcas, mundos, bases de tela, bases de hilaza) con sus términos
 * de búsqueda, creación inline y sincronización desde props.
 */
import type { OptionParams } from '@/domain/Production';
import { toIdOrNull } from '@/composables/useProductSheetForm';
import axios from 'axios';
import { computed, ref, type Ref } from 'vue';

// ─── Tipos ────────────────────────────────────────────────────────────────────

interface CatalogConfig {
    getTerm: () => string;
    getOptions: () => OptionParams[];
    setOptions: (options: OptionParams[]) => void;
    assign: (id: number) => void;
    successLabel: string;
}

export type CatalogKey = 'sections' | 'groups' | 'brands' | 'worlds' | 'fabric_bases' | 'yarn_bases';

// ─── Constante NA ─────────────────────────────────────────────────────────────

export const FABRIC_BASE_NA_OPTION: OptionParams = {
    id: 0,
    name: 'N/A - No aplica',
};

// ─── Composable ───────────────────────────────────────────────────────────────

interface UseCatalogOptionsParams {
    form: Ref<{
        section_id: number | null;
        group_id: number | null;
        brand_id: number | null;
        world_id: number | null;
        fabric_base_id: number | null;
        yarn_base_id: number | null;
        woven_product: boolean;
    }>;
    isReadonly: Ref<boolean>;
    setError: (message: string, type?: 'error' | 'warning' | 'info') => void;
}

export function useCatalogOptions(params: UseCatalogOptionsParams) {
    const { form, isReadonly, setError } = params;

    // ── Estado ──────────────────────────────────────────────────────────────

    const sectionOptions = ref<OptionParams[]>([]);
    const groupOptions = ref<OptionParams[]>([]);
    const brandOptions = ref<OptionParams[]>([]);
    const worldOptions = ref<OptionParams[]>([]);
    const baseFabricOptions = ref<OptionParams[]>([]);
    const yarnBaseOptions = ref<OptionParams[]>([]);

    const sectionSearchTerm = ref('');
    const groupSearchTerm = ref('');
    const brandSearchTerm = ref('');
    const worldSearchTerm = ref('');
    const fabricBaseSearchTerm = ref('');
    const yarnBaseSearchTerm = ref('');

    // ── Fabric Base Options (con N/A cuando es tejido) ──────────────────────

    const fabricBaseOptions = computed<OptionParams[]>(() => {
        const bases = [...baseFabricOptions.value];

        if (!form.value.woven_product) return bases;

        const hasEquivalentNaOption = bases.some((option) => {
            const normalizedName = String(option?.name ?? '').trim().toLocaleLowerCase('es-CO');
            return Number(option?.id) === 0
                || normalizedName.includes('no aplica')
                || normalizedName === 'na'
                || normalizedName === 'n/a';
        });

        return hasEquivalentNaOption ? bases : [FABRIC_BASE_NA_OPTION, ...bases];
    });

    // ── Config de catálogos ─────────────────────────────────────────────────

    const selectCreationConfig: Record<CatalogKey, CatalogConfig> = {
        sections: {
            getTerm: () => sectionSearchTerm.value,
            getOptions: () => sectionOptions.value,
            setOptions: (opts) => { sectionOptions.value = opts; },
            assign: (id) => { form.value.section_id = id; },
            successLabel: 'Sección',
        },
        groups: {
            getTerm: () => groupSearchTerm.value,
            getOptions: () => groupOptions.value,
            setOptions: (opts) => { groupOptions.value = opts; },
            assign: (id) => { form.value.group_id = id; },
            successLabel: 'Grupo',
        },
        brands: {
            getTerm: () => brandSearchTerm.value,
            getOptions: () => brandOptions.value,
            setOptions: (opts) => { brandOptions.value = opts; },
            assign: (id) => { form.value.brand_id = id; },
            successLabel: 'Marca',
        },
        worlds: {
            getTerm: () => worldSearchTerm.value,
            getOptions: () => worldOptions.value,
            setOptions: (opts) => { worldOptions.value = opts; },
            assign: (id) => { form.value.world_id = id; },
            successLabel: 'Mundo',
        },
        fabric_bases: {
            getTerm: () => fabricBaseSearchTerm.value,
            getOptions: () => baseFabricOptions.value,
            setOptions: (opts) => { baseFabricOptions.value = opts; },
            assign: (id) => { form.value.fabric_base_id = id; },
            successLabel: 'Base tela',
        },
        yarn_bases: {
            getTerm: () => yarnBaseSearchTerm.value,
            getOptions: () => yarnBaseOptions.value,
            setOptions: (opts) => { yarnBaseOptions.value = opts; },
            assign: (id) => { form.value.yarn_base_id = id; },
            successLabel: 'Base hilaza',
        },
    };

    // ── Sincronizar opciones desde props ────────────────────────────────────

    function syncCatalogOptionsFromProps(propsData: {
        sections: OptionParams[];
        groups: OptionParams[];
        brands: OptionParams[];
        worlds: OptionParams[];
        fabricBases: OptionParams[];
        yarnBases: OptionParams[];
    }): void {
        sectionOptions.value = Array.isArray(propsData.sections) ? [...propsData.sections] : [];
        groupOptions.value = Array.isArray(propsData.groups) ? [...propsData.groups] : [];
        brandOptions.value = Array.isArray(propsData.brands) ? [...propsData.brands] : [];
        worldOptions.value = Array.isArray(propsData.worlds) ? [...propsData.worlds] : [];
        baseFabricOptions.value = Array.isArray(propsData.fabricBases) ? [...propsData.fabricBases] : [];
        yarnBaseOptions.value = Array.isArray(propsData.yarnBases) ? [...propsData.yarnBases] : [];
    }

    // ── Normalización ───────────────────────────────────────────────────────

    function normalizeCollectionName(value: string): string {
        return value.trim().replace(/\s+/g, ' ');
    }

    // ── Crear opción inline ─────────────────────────────────────────────────

    async function createCatalogOption(catalogKey: CatalogKey): Promise<void> {
        if (isReadonly.value) return;

        const config = selectCreationConfig[catalogKey];
        const name = normalizeCollectionName(config.getTerm());

        if (!name) {
            setError(`Escribe un nombre para agregar ${config.successLabel.toLowerCase()}.`, 'warning');
            return;
        }

        const currentOptions = config.getOptions();
        const exists = currentOptions.find(
            (option) => String(option.name).trim().toLowerCase() === name.toLowerCase(),
        );

        if (exists) {
            const existingId = toIdOrNull(exists.id);
            if (existingId) config.assign(existingId);
            setError(`${config.successLabel} ya existe y fue seleccionada.`, 'info');
            return;
        }

        try {
            const response = await axios.post(route('produccion.product-sheets.parameters.store-option'), {
                key: catalogKey,
                name,
            });

            const created = response.data?.data;
            const createdId = toIdOrNull(created?.id ?? null);
            const createdName = normalizeCollectionName(String(created?.name ?? name));

            if (!createdId) {
                setError(`No se pudo crear ${config.successLabel.toLowerCase()}.`);
                return;
            }

            config.setOptions([...currentOptions, { id: createdId, name: createdName }]);
            config.assign(createdId);
            setError(`${config.successLabel} agregada y seleccionada.`, 'info');
        } catch (err: unknown) {
            if (axios.isAxiosError(err)) {
                const message = err.response?.data?.message;
                const validationError = err.response?.data?.errors
                    ? Object.values(err.response.data.errors).flat()[0]
                    : null;
                setError(
                    (validationError as string) ?? message ?? `No se pudo crear ${config.successLabel.toLowerCase()}.`,
                );
                return;
            }
            setError(`No se pudo crear ${config.successLabel.toLowerCase()}.`);
        }
    }

    return {
        // Opciones
        sectionOptions,
        groupOptions,
        brandOptions,
        worldOptions,
        baseFabricOptions,
        yarnBaseOptions,
        fabricBaseOptions,

        // Términos de búsqueda
        sectionSearchTerm,
        groupSearchTerm,
        brandSearchTerm,
        worldSearchTerm,
        fabricBaseSearchTerm,
        yarnBaseSearchTerm,

        // Acciones
        syncCatalogOptionsFromProps,
        createCatalogOption,
        normalizeCollectionName,
    };
}
