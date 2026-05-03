<script setup lang="ts">
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown, Search, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';

export interface SearchableOption {
    value: string | number;
    label: string;
    subtitle?: string;
    disabled?: boolean;
}

const props = withDefaults(
    defineProps<{
        modelValue: string | number | null;
        options: SearchableOption[];
        placeholder?: string;
        emptyText?: string;
        searchPlaceholder?: string;
        disabled?: boolean;
        clearable?: boolean;
        /** Optional id forwarded to visible button */
        buttonId?: string;
        compact?: boolean;
    }>(),
    {
        placeholder: '',
        emptyText: 'No matches.',
        searchPlaceholder: 'Search…',
        disabled: false,
        clearable: true,
        buttonId: undefined,
        compact: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string | number | null];
    openChange: [open: boolean];
}>();

const open = ref(false);
const query = ref('');
const rootRef = ref<HTMLElement | null>(null);
const listRef = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);
const highlighted = ref(-1);

const normalized = (s: string) => s.normalize('NFKD').toLowerCase();

const selectedOption = computed(() =>
    props.options.find((o) => String(o.value) === String(props.modelValue ?? '')),
);

const filtered = computed(() => {
    const q = normalized(query.value.trim());
    if (!q.length) return props.options;
    return props.options.filter((o) => {
        const ln = normalized(o.label);
        const sn = o.subtitle ? normalized(o.subtitle) : '';
        return ln.includes(q) || sn.includes(q);
    });
});

watch(open, async (isOpen) => {
    emit('openChange', isOpen);
    if (isOpen) {
        highlighted.value = -1;
        query.value = '';
        await nextTick();
        inputRef.value?.focus();
    }
});

watch(
    () => props.options,
    () => {
        if (highlighted.value >= filtered.value.length) highlighted.value = -1;
    },
);

onClickOutside(rootRef, () => {
    open.value = false;
});

function toggle() {
    if (props.disabled) return;
    open.value = !open.value;
}

function pick(o: SearchableOption, ev?: MouseEvent) {
    ev?.preventDefault();
    if (o.disabled) return;
    emit('update:modelValue', o.value);
    open.value = false;
}

function clear(ev: MouseEvent) {
    ev.stopPropagation();
    emit('update:modelValue', null);
}

function syncHighlight() {
    if (highlighted.value < 0) return;
    const el = listRef.value?.querySelector<HTMLElement>(
        `[data-opt-idx="${highlighted.value}"]`,
    );
    el?.scrollIntoView({ block: 'nearest' });
}

function onKeydown(e: KeyboardEvent) {
    if (!open.value) {
        if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
            if (props.disabled) return;
            e.preventDefault();
            open.value = true;
        }
        return;
    }

    const list = filtered.value;
    switch (e.key) {
        case 'Escape':
            e.preventDefault();
            open.value = false;
            break;
        case 'ArrowDown': {
            e.preventDefault();
            const next =
                highlighted.value < list.length - 1 ? highlighted.value + 1 : 0;
            highlighted.value = next;
            nextTick(syncHighlight);
            break;
        }
        case 'ArrowUp': {
            e.preventDefault();
            const prev =
                highlighted.value > 0 ? highlighted.value - 1 : list.length - 1;
            highlighted.value = list.length === 0 ? -1 : prev;
            nextTick(syncHighlight);
            break;
        }
        case 'Enter': {
            e.preventDefault();
            const hi = filtered.value[highlighted.value];
            if (hi && !hi.disabled) pick(hi);
            break;
        }
        case 'Home':
            e.preventDefault();
            highlighted.value = list.length ? 0 : -1;
            nextTick(syncHighlight);
            break;
        case 'End':
            e.preventDefault();
            highlighted.value = list.length ? list.length - 1 : -1;
            nextTick(syncHighlight);
            break;
        default:
    }
}

</script>

<template>
    <div
        ref="rootRef"
        class="relative min-w-[10rem]"
        :tabindex="disabled ? undefined : 0"
        @keydown="onKeydown"
    >
        <div
            :class="
                cn(
                    'flex w-full items-stretch overflow-hidden rounded-lg border border-input bg-background shadow-sm transition-[box-shadow,border-color] hover:border-muted-foreground/25 focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/35',
                    disabled && 'pointer-events-none opacity-60 cursor-not-allowed',
                    compact ? 'min-h-[2rem] text-sm' : 'min-h-[2.55rem] text-sm',
                )
            "
        >
            <button
                :id="buttonId"
                type="button"
                role="combobox"
                :aria-expanded="open"
                :disabled="disabled"
                data-searchable-trigger
                :class="
                    cn(
                        'flex min-w-0 flex-1 items-center gap-2 bg-transparent px-3 py-2 text-start outline-none rtl:text-end',
                        compact && 'px-2.5 py-1.5',
                    )
                "
                @click="toggle"
            >
                <ChevronsUpDown class="size-4 shrink-0 opacity-55 text-muted-foreground" aria-hidden />
                <span
                    class="min-w-0 flex-1 truncate text-start rtl:text-end"
                    :class="selectedOption ? 'text-foreground' : 'text-muted-foreground'"
                >
                    <template v-if="selectedOption">{{ selectedOption.label }}</template>
                    <template v-else>{{ placeholder }}</template>
                </span>
            </button>
            <button
                v-if="clearable && modelValue != null && modelValue !== '' && !disabled"
                type="button"
                tabindex="-1"
                class="inline-flex shrink-0 items-center border-s border-border/60 px-2.5 text-muted-foreground hover:bg-muted/80"
                aria-label="Clear selection"
                @click="clear($event)"
            >
                <X class="size-3.5" />
            </button>
        </div>

        <Transition
            enter-active-class="duration-120 ease-out"
            enter-from-class="opacity-0 scale-[0.98] translate-y-0.5"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="absolute left-0 right-0 z-[60] mt-2 overflow-hidden rounded-xl border border-border bg-popover text-popover-foreground shadow-xl ring-1 ring-black/[0.04] dark:ring-white/[0.06]"
                role="listbox"
            >
                <!-- Search -->
                <div class="sticky top-0 z-[1] border-b border-border bg-muted/30 px-2 py-2 backdrop-blur supports-[backdrop-filter]:bg-muted/45">
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden
                        />
                        <input
                            ref="inputRef"
                            v-model="query"
                            type="text"
                            autocomplete="off"
                            autocapitalize="off"
                            spellcheck="false"
                            class="h-10 w-full rounded-lg border border-border bg-background py-2 ps-9 pe-3 text-sm shadow-inner outline-none placeholder:text-muted-foreground focus:border-ring focus:ring-[3px] focus:ring-ring/30"
                            :placeholder="searchPlaceholder"
                        />
                    </div>
                    <p class="mt-1 px-1 text-[11px] text-muted-foreground tabular-nums">
                        {{ filtered.length }}/{{ props.options.length }}
                    </p>
                </div>

                <div
                    ref="listRef"
                    class="max-h-[min(18rem,var(--radix-popover-content-available-height,18rem))] overflow-y-auto overscroll-contain px-1 py-1 scroll-py-1"
                >
                    <template v-if="filtered.length === 0">
                        <div class="px-3 py-8 text-center text-sm text-muted-foreground">
                            {{ emptyText }}
                        </div>
                    </template>
                    <button
                        v-for="(opt, idx) in filtered"
                        :key="String(opt.value)"
                        type="button"
                        role="option"
                        class="relative flex w-full flex-col rounded-lg px-2.5 py-2 text-start text-sm transition-colors"
                        data-searchable-opt
                        :data-opt-idx="idx"
                        :class="[
                            opt.disabled ? 'opacity-45 cursor-not-allowed' : 'cursor-pointer hover:bg-accent/85',
                            idx === highlighted && !opt.disabled
                                ? 'bg-accent text-accent-foreground ring-1 ring-ring/25'
                                : '',
                            String(opt.value) === String(modelValue ?? '') && opt.disabled !== true
                                ? 'bg-primary/8 text-primary-foreground'
                                : '',
                        ]"
                        :disabled="opt.disabled === true"
                        @mouseenter="highlighted = idx"
                        @click="pick(opt, $event)"
                    >
                        <div class="flex items-center gap-2 min-w-0">
                            <Check
                                class="size-3.5 shrink-0 text-primary opacity-0"
                                :class="
                                    String(opt.value) === String(modelValue ?? '') ? '!opacity-100' : ''
                                "
                                aria-hidden
                            />
                            <span class="min-w-0 flex-1 truncate font-medium">{{ opt.label }}</span>
                        </div>
                        <span
                            v-if="opt.subtitle"
                            class="ms-7 text-[11px] text-muted-foreground leading-tight truncate"
                        >
                            {{ opt.subtitle }}
                        </span>
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
