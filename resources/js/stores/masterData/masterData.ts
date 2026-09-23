import axios from 'axios';
import { defineStore } from 'pinia';

interface Group {
    id: string | number;
    name: string;
}

interface MasterDataState {
    groups: Group[];
    groupsError: string | null;
    groupsLoaded: boolean;
    groupsLoading: boolean;
}

// Token de la última llamada en curso: si dos fetch se solapan (uno normal + uno
// forzado casi al mismo tiempo), descarta la respuesta que ya no es la más reciente
// en vez de dejar que un dato viejo pise a uno más nuevo.
let requestToken = 0;

export const useMasterDataStore = defineStore('masterData', {
    state: (): MasterDataState => ({
        groups: [],
        groupsError: null,
        groupsLoaded: false,
        groupsLoading: false,
    }),
    actions: {
        async fetchGroups(force = false): Promise<void> {
            if (!force && (this.groupsLoaded || this.groupsLoading)) return;
            const token = ++requestToken;
            this.groupsLoading = true;
            this.groupsError = null;
            try {
                const response = await axios.get('/categorias/grupos');
                if (token !== requestToken) return;
                this.groups = response.data.map((g: any) => ({
                    id: g.id,
                    name: g.name,
                }));
                this.groupsLoaded = true;
            } catch (error) {
                if (token !== requestToken) return;
                this.groupsError = 'No se pudieron cargar las categorías proveedor/cliente.';
                console.error('Error cargando grupos:', error);
            } finally {
                if (token === requestToken) this.groupsLoading = false;
            }
        },
        invalidate(): void {
            this.groupsLoaded = false;
        },
    },
});
