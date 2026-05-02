<script setup lang="ts">
defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <!-- Custom RapiDev POS mark: receipt strips + block “R” (replaces Laravel lattice icon) -->
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 42" fill="currentColor" v-bind="$attrs">
        <!-- Receipt / barcode motif -->
        <rect x="5" y="11" width="3" height="20" rx="1.25" />
        <rect x="10" y="11" width="4.5" height="20" rx="1.25" opacity="0.9" />
        <rect x="16.5" y="11" width="2.5" height="20" rx="1" />

        <!-- Letter R -->
        <rect x="22" y="11" width="5.5" height="21" rx="2.25" />
        <rect x="22" y="11" width="15" height="5.5" rx="2.25" />
        <rect x="22" y="20" width="11" height="4.5" rx="2" />
        <rect x="33" y="11" width="5" height="13" rx="2" />
        <path d="M 29 22 L 39.5 40 H 34.2 L 25 25 Z" />
    </svg>
</template>
