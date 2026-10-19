import type { Area } from '@/domain/Settings';
import axios from 'axios';
import { computed, ref } from 'vue';

/**
 * Catálogo de áreas (tabla `areas`). Todas las pantallas que necesiten
 * listar áreas deben usar este composable en lugar de consultar el endpoint por su cuenta.
 */
export function useAreas() {
    const areas = ref<Area[]>([]);
    const areaOptions = computed(() => areas.value.map(({ id, name }) => ({ id, name })));

    async function fetchAreas(): Promise<void> {
        try {
            const { data } = await axios.get<Area[]>('/areas/list');
            areas.value = Array.isArray(data) ? data : [];
        } catch (error) {
            console.error('Error al cargar áreas:', error);
            areas.value = [];
        }
    }

    return { areas, areaOptions, fetchAreas };
}
