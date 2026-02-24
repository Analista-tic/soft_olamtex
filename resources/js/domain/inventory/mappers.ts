export function mapWarehouseDto(dto: { id: number; name: string; observation: string; created_at?: string | null; updated_at?: string | null; }) {
    return {
        id: dto.id,
        name: dto.name,
        observation: dto.observation,
        created_at: dto.created_at ?? undefined,
        updated_at: dto.updated_at ?? undefined,
    };
}
