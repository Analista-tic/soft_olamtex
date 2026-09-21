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

export const useMasterDataStore = defineStore('masterData', {
    state: (): MasterDataState => ({
        groups: [],
        groupsError: null,
        groupsLoaded: false,
        groupsLoading: false,
    }),
    actions: {
        async fetchGroups(): Promise<void> {
            if (this.groupsLoaded || this.groupsLoading) return;
            this.groupsLoading = true;
            this.groupsError = null;
            try {
                const response = await axios.get('/categorias/grupos');
                this.groups = response.data.map((g: any) => ({
                    id: g.id,
                    name: g.name,
                }));
                this.groupsLoaded = true;
            } catch (error) {
                this.groupsError = 'No se pudieron cargar las categorías proveedor/cliente.';
                console.error('Error cargando grupos:', error);
            } finally {
                this.groupsLoading = false;
            }
        },
    },
});
