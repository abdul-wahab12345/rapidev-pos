<script setup lang="ts">
import {
    SelectRoot,
    type SelectRootEmits,
    type SelectRootProps,
    useForwardPropsEmits,
} from 'radix-vue';

const props  = defineProps<SelectRootProps>();
const emits  = defineEmits<SelectRootEmits>();
const forward = useForwardPropsEmits(props, emits);
</script>

<template>
    <SelectRoot v-bind="forward">
        <slot />
    </SelectRoot>
</template>
