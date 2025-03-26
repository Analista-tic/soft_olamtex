<template>
    <div>
        <button @click="filterComplete(true)">Filtrar Completos</button>
        <button @click="filterComplete(false)">Filtrar Incompletos</button>
        <button @click="clearFilter">Limpiar Filtro</button>
    </div>
</template>

<script setup lang="ts">

const filterComplete = (isComplete: boolean) => {
    // Emitir evento para filtrar productos completos o incompletos
    // Este código depende de cómo se maneje el estado en el componente padre
    // Aquí solo se muestra un ejemplo básico
    const event = new CustomEvent('filter-complete', { detail: isComplete });
    window.dispatchEvent(event);
};

const clearFilter = () => {
    // Emitir evento para limpiar el filtro
    const event = new CustomEvent('clear-filter');
    window.dispatchEvent(event);
};
</script>
