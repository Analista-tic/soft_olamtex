import axios from 'axios';
import { defineStore } from 'pinia';

interface Group {
    id: string | number;
    name: string;
}

interface MasterDataState {
    groups: Group[];
    groupsLoaded: boolean;
    groupsLoading: boolean;
}

export const useMasterDataStore = defineStore('masterData', {
    state: (): MasterDataState => ({
        groups: [],
        groupsLoaded: false,
        groupsLoading: false,
    }),
    actions: {
        async fetchGroups(): Promise<void> {
            if (this.groupsLoaded || this.groupsLoading) return;
            this.groupsLoading = true;
            try {
                const response = await axios.get('/categorias/grupos');
                this.groups = response.data.map((g: any) => ({
                    id: g.id,
                    name: g.name,
                }));
                this.groupsLoaded = true;
            } catch (error) {
                console.error('Error cargando grupos:', error);
            } finally {
                this.groupsLoading = false;
            }
        },
    },
});
