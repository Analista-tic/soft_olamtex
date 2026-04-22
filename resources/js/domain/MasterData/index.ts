import axios from 'axios';
import type { Parameter, ParameterDeletionCheck, ParameterListQuery, ParameterPayload } from './types';

export type {
    AdditionalContact,
    AdditionalField,
    AdditionalFieldDialogFormState,
    AdditionalFieldPayload,
    AdditionalFieldType,
    CategoryGroup,
    CategorySubGroup,
    Contact,
    ContactIdType,
    ContactPersonType,
    ContactType,
    ExtraFields,
    Item,
    Parameter,
    ParameterDeletionCheck,
    ParameterDialogFormState,
    ParameterListQuery,
    ParameterPayload,
    ParameterStatusFilter,
} from './types';

export type { AdditionalContactDto, CategorySubGroupDto, ContactDto, ContactIdTypeDto, ContactPersonTypeDto, ContactTypeDto, ItemDto } from './dto';
export {
    mapAdditionalFieldRecord,
    mapAdditionalFieldToDialogForm,
    mapCategorySubGroupDto,
    mapContactDto,
    mapItemDto,
    mapItemsDto,
    mapParameterToDialogForm,
    normalizeBooleanFlag,
} from './mappers';

const PARAMETERS_ENDPOINT = '/parametros';
const PARAMETERS_DATA_ENDPOINT = '/parametros/data';

export function getParameters(params: ParameterListQuery = {}) {
    return axios.get<{ data: Parameter[] }>(PARAMETERS_DATA_ENDPOINT, { params });
}

export function createParameter(payload: ParameterPayload) {
    return axios.post(PARAMETERS_ENDPOINT, payload);
}

export function updateParameter(id: number, payload: ParameterPayload) {
    return axios.put(`${PARAMETERS_ENDPOINT}/${id}`, payload);
}

export function deleteParameter(id: number) {
    return axios.delete(`${PARAMETERS_ENDPOINT}/${id}`);
}

export function getParameterDeletionCheck(id: number) {
    return axios.get<ParameterDeletionCheck>(`${PARAMETERS_ENDPOINT}/${id}/deletion-check`);
}
