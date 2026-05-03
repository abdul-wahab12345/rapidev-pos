<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Building2, FileText, Search, ShoppingCart, User, X } from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface SearchResult {
    type: 'customer' | 'supplier' | 'sale' | 'purchase_order';
    id: string;
    title: string;
    subtitle: string | null;
    url: string;
}

const open = ref(false);
const query = ref('');
const results = ref<SearchResult[]>([]);
const loading = ref(false);
const activeIndex = ref(0);
const inputRef = ref<HTMLInputElement | null>(null);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

function openSearch() {
    open.value = true;
    nextTick(() => inputRef.value?.focus());
}

function closeSearch() {
    open.value = false;
    query.value = '';
    results.value = [];
    activeIndex.value = 0;
}

function onKeydown(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        open.value ? closeSearch() : openSearch();
    }
    if (!open.value) return;
    if (e.key === 'Escape') closeSearch();
    if (e.key === 'ArrowDown') { e.preventDefault(); activeIndex.value = Math.min(activeIndex.value + 1, results.value.length - 1); }
    if (e.key === 'ArrowUp')   { e.preventDefault(); activeIndex.value = Math.max(activeIndex.value - 1, 0); }
    if (e.key === 'Enter' && results.value[activeIndex.value]) navigate(results.value[activeIndex.value]);
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

watch(query, (val) => {
    activeIndex.value = 0;
    if (debounceTimer) clearTimeout(debounceTimer);
    if (val.trim().length < 2) { results.value = []; return; }
    loading.value = true;
    debounceTimer = setTimeout(async () => {
        try {
            const res = await fetch(route('global-search') + '?q=' + encodeURIComponent(val.trim()));
            results.value = await res.json();
        } finally {
            loading.value = false;
        }
    }, 300);
});

function navigate(item: SearchResult) {
    closeSearch();
    router.visit(item.url);
}

const typeConfig: Record<SearchResult['type'], { labelKey: string; icon: any; color: string }> = {
    customer:       { labelKey: 'search.typeCustomer',      icon: User,         color: 'text-blue-500' },
    supplier:       { labelKey: 'search.typeSupplier',      icon: Building2,    color: 'text-orange-500' },
    sale:           { labelKey: 'search.typeSale',          icon: FileText,     color: 'text-green-500' },
    purchase_order: { labelKey: 'search.typePurchaseOrder', icon: ShoppingCart, color: 'text-purple-500' },
};
</script>

<template>
    <!-- Trigger button -->
    <button
        @click="openSearch"
        class="flex items-center gap-2 rounded-md border bg-muted/50 px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted transition-colors w-[600px] justify-between"
    >
        <span class="flex items-center gap-2">
            <Search class="h-3.5 w-3.5" />
            {{ t('search.placeholder') }}
        </span>
        <kbd class="pointer-events-none hidden rounded border bg-background px-1.5 py-0.5 font-mono text-[10px] font-medium sm:inline-flex items-center gap-0.5">
            <span class="text-xs">⌘</span>K
        </kbd>
    </button>

    <!-- Modal overlay -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-50 flex items-start justify-center pt-[15vh] px-4" @click.self="closeSearch">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50" @click="closeSearch" />

                <!-- Panel -->
                <div class="relative z-10 w-full max-w-lg rounded-xl border bg-background shadow-2xl overflow-hidden">
                    <!-- Input row -->
                    <div class="flex items-center gap-3 border-b px-4 py-3">
                        <Search class="h-4 w-4 shrink-0 text-muted-foreground" />
                        <input
                            ref="inputRef"
                            v-model="query"
                            :placeholder="t('search.inputPlaceholder')"
                            class="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                            autocomplete="off"
                        />
                        <button v-if="query" @click="query = ''" class="text-muted-foreground hover:text-foreground">
                            <X class="h-4 w-4" />
                        </button>
                        <kbd @click="closeSearch" class="cursor-pointer rounded border bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground">Esc</kbd>
                    </div>

                    <!-- Results -->
                    <div class="max-h-80 overflow-y-auto">
                        <!-- Loading -->
                        <div v-if="loading" class="py-8 text-center text-sm text-muted-foreground">
                            {{ t('search.searching') }}
                        </div>

                        <!-- Empty -->
                        <div v-else-if="query.trim().length >= 2 && results.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                            {{ t('search.noResults') }}
                        </div>

                        <!-- Hint before typing -->
                        <div v-else-if="query.trim().length < 2" class="py-8 text-center text-sm text-muted-foreground">
                            {{ t('search.hint') }}
                        </div>

                        <!-- Result rows -->
                        <ul v-else>
                            <li
                                v-for="(item, i) in results"
                                :key="item.type + item.id"
                                @click="navigate(item)"
                                @mouseenter="activeIndex = i"
                                class="flex items-center gap-3 cursor-pointer px-4 py-2.5 transition-colors"
                                :class="activeIndex === i ? 'bg-muted' : 'hover:bg-muted/50'"
                            >
                                <!-- Type icon -->
                                <component
                                    :is="typeConfig[item.type].icon"
                                    class="h-4 w-4 shrink-0"
                                    :class="typeConfig[item.type].color"
                                />

                                <!-- Text -->
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium">{{ item.title }}</div>
                                    <div v-if="item.subtitle" class="truncate text-xs text-muted-foreground">{{ item.subtitle }}</div>
                                </div>

                                <!-- Type label -->
                                <span class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
                                    {{ t(typeConfig[item.type].labelKey) }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
