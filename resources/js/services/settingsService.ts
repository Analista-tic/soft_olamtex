import axios from 'axios';

export type ParameterStatusFilter = 'all' | 'active' | 'inactive';

export type ParameterFilters = {
    search?: string;
    status?: ParameterStatusFilter;
    sort_by?: 'label' | 'key';
    sort_direction?: 'asc' | 'desc';
};

export type ParameterPayload = {
    key: string;
    label: string;
    description?: string;
    value: unknown;
    status?: boolean;
};

export const getParameters = (filters: ParameterFilters = {}) => {
    return axios.get('/parametros/data', {
        params: filters,
    });
};

export const createParameter = (payload: ParameterPayload) => {
    return axios.post('/parametros', payload);
};

export const updateParameter = (id: string | number, payload: ParameterPayload) => {
    return axios.put(`/parametros/${id}`, payload);
};

export const deleteParameter = (id: string | number) => {
    return axios.delete(`/parametros/${id}`);
};

export default {
    getParameters,
    createParameter,
    updateParameter,
    deleteParameter,
};
