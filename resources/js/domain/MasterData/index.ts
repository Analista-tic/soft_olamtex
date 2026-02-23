import axios from 'axios';
import type { Parameter, ParameterListQuery, ParameterPayload } from './types';

export type {
	AdditionalContact,
	Contact,
	CategoryGroup,
	Item,
	CategorySubGroup,
	AdditionalField,
	AdditionalFieldType,
	AdditionalFieldPayload,
	ExtraFields,
	Parameter,
	ParameterStatusFilter,
	ParameterListQuery,
	ParameterPayload,
} from './types';

export type { AdditionalContactDto, ContactDto, ItemDto, CategorySubGroupDto } from './dto';
export { mapContactDto, mapItemDto, mapItemsDto, mapCategorySubGroupDto } from './mappers';

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
