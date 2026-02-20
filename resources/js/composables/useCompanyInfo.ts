import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedData, CompanyInfo } from '@/domain/shared/types';

export function useCompanyInfo() {
    const page = usePage<SharedData>();
    const company = computed<CompanyInfo | null>(() => page.props.company ?? null);
    const logoUrl = computed(() => company.value?.logo_url ?? '/images/empty-picture.jpg');

    return {
        company,
        logoUrl,
    };
}

