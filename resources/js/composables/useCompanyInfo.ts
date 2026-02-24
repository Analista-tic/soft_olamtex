import type { CompanyInfo, SharedData } from '@/domain/shared/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useCompanyInfo() {
    const page = usePage<SharedData>();
    const company = computed<CompanyInfo | null>(() => page.props.company ?? null);
    const logoUrl = computed(() => company.value?.logo_url ?? '/images/empty-picture.jpg');

    return {
        company,
        logoUrl,
    };
}
