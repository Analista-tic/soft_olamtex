import type { Ref } from 'vue';
import { computed } from 'vue';

export interface ProductSheetValidationSource {
    section_id: number | null;
    group_id: number | null;
    brand_id: number | null;
    world_id: number | null;
    description?: string | null;
    fabric_base_id: number | null;
    billing_price?: number | null;
    operational_list_id: number | null;
    yarn_base_id?: number | null;
    woven_product?: boolean;
}

export interface ProductSheetFieldErrors {
    reference: string;
    section_id: string;
    group_id: string;
    brand_id: string;
    world_id: string;
    description: string;
    fabric_base_id: string;
    billing_price: string;
    yarn_base_id: string;
    operational_list_id: string;
}

export function useProductSheetValidation<T extends ProductSheetValidationSource>(params: {
    form: Ref<T>;
    activeReference: Ref<string>;
    requiresOperationalList?: Ref<boolean>;
    hasOperationalListConfigured?: Ref<boolean>;
    requiresYarnBase?: Ref<boolean>;
}) {
    const requiresOperationalList = params.requiresOperationalList ?? computed(() => false);
    const requiresYarnBase = params.requiresYarnBase ?? computed(() => false);
    const hasOperationalListConfigured = params.hasOperationalListConfigured ?? computed(() => false);

    const validationErrors = computed<string[]>(() => {
        const errors: string[] = [];
        const form = params.form.value;

        if (!form.section_id) errors.push('Sección es requerida.');
        if (!form.group_id) errors.push('Grupo es requerido.');
        if (!form.brand_id) errors.push('Marca es requerida.');
        if (!form.world_id) errors.push('Mundo es requerido.');
        if (!form.description?.trim()) errors.push('El nombre del producto es obligatorio.');
        if (!requiresYarnBase.value && !form.fabric_base_id) errors.push('Base Tela es requerida.');
        if (!params.activeReference.value.trim()) errors.push('Referencia es requerida.');

        if (requiresOperationalList.value && !hasOperationalListConfigured.value && !form.operational_list_id) {
            errors.push(
                'Configura el listado operacional: haz clic en "Configurar procesos" y añade al menos una operación para el proceso Muestras.',
            );
        }

        if (requiresYarnBase.value && !form.yarn_base_id) {
            errors.push('Base hilaza es requerida cuando el producto es tejido.');
        }

        return errors;
    });

    const fieldErrors = computed<ProductSheetFieldErrors>(() => {
        const form = params.form.value;
        return {
            reference: !params.activeReference.value.trim() ? 'Referencia es requerida.' : '',
            section_id: !form.section_id ? 'Sección es requerida.' : '',
            group_id: !form.group_id ? 'Grupo es requerido.' : '',
            brand_id: !form.brand_id ? 'Marca es requerida.' : '',
            world_id: !form.world_id ? 'Mundo es requerido.' : '',
            description: !form.description?.trim() ? 'El nombre del producto es obligatorio.' : '',
            fabric_base_id: !requiresYarnBase.value && !form.fabric_base_id ? 'Base Tela es requerida.' : '',
            billing_price: '',
            yarn_base_id: requiresYarnBase.value && !form.yarn_base_id ? 'Base hilaza es requerida.' : '',
            operational_list_id:
                requiresOperationalList.value && !hasOperationalListConfigured.value && !form.operational_list_id
                    ? 'Configura el listado operacional (Configurar procesos).'
                    : '',
        };
    });

    const isValid = computed(() => validationErrors.value.length === 0);

    return {
        validationErrors,
        fieldErrors,
        isValid,
    };
}
