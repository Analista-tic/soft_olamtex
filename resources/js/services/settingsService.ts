/**
 * Settings API service.
 * Kept for backward compatibility. Settings-related API calls may be
 * in domain/Settings or made directly via axios in components.
 */
import axios from 'axios';

export async function fetchUser() {
    return axios.get('/user');
}

export async function fetchCompanySettings() {
    return axios.get('/configuracion/empresa');
}

export async function updateCompanySettings(payload: Record<string, unknown>) {
    return axios.patch('/configuracion/empresa', payload);
}
