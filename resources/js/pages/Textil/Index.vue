<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { uiPatterns } from '@/lib/ui-patterns';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Package } from 'lucide-vue-next';

const breadcrumbs = [{ title: 'Kardex de Telas', href: '/kardex-telas' }];

const receptionReturnParam = 'return_to';
const receptionRoute = '/kardex-telas/recepcion';
const kardexRoute = '/kardex-telas';

function openReception() {
    const url = `${receptionRoute}?${receptionReturnParam}=${encodeURIComponent(kardexRoute)}`;
    router.visit(url);
}
</script>

<template>
    <Head title="Kardex de Telas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="rounded-xl border border-dashed border-muted-foreground/60 bg-card/70 p-8 text-center shadow-sm">
            <div class="flex items-center justify-center gap-3">
                <p :class="uiPatterns.typography.pageTitle">Bienvenido al Kardex de Telas</p>
                <Button @click="openReception" variant="outline" size="sm">
                    <Package class="h-4 w-4" />
                    Ver recepciones
                </Button>
            </div>
            <p :class="`mt-4 ${uiPatterns.typography.pageSubtitle}`">
                Aquí podrás encontrar el control de rollos y movimientos. Por el momento no hay contenido específico para esta vista.
            </p>
        </div>
    </AppLayout>
</template>

