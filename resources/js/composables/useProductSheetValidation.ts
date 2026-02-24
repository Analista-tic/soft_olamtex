import type { Ref } from 'vue';
import { computed } from 'vue';

export interface ProductSheetValidationSource {
    section_id: number | null;
    group_id: number | null;
    brand_id: number | null;
    world_id: number | null;
    fabric_base_id: number | null;
    operational_list_id: number | null;
}

export function useProductSheetValidation<T extends ProductSheetValidationSource>(params: {
    form: Ref<T>;
    activeReference: Ref<string>;
    requiresOperationalList: Ref<boolean>;
}) {
    const validationErrors = computed<string[]>(() => {
        const errors: string[] = [];
        const form = params.form.value;

        if (!form.section_id) errors.push('Sección es requerida.');
        if (!form.group_id) errors.push('Grupo es requerido.');
        if (!form.brand_id) errors.push('Marca es requerida.');
        if (!form.world_id) errors.push('Mundo es requerido.');
        if (!form.fabric_base_id) errors.push('Base es requerida.');
        if (!params.activeReference.value.trim()) errors.push('Referencia es requerida.');

        if (params.requiresOperationalList.value && !form.operational_list_id) {
            errors.push('Selecciona un listado operacional para Desarrollo de muestras.');
        }

        return errors;
    });

    const isValid = computed(() => validationErrors.value.length === 0);

    return {
        validationErrors,
        isValid,
    };
}

