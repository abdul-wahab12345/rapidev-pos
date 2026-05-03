<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { usePage } from '@inertiajs/vue3';
import { Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

export type ReportFilterLabels = {
    city?: string | null;
    area?: string | null;
    from?: string | null;
    to?: string | null;
    q?: string | null;
};

const props = defineProps<{
    filterLabels: ReportFilterLabels;
}>();

const { t, locale } = useI18n();
const page = usePage();

const tenantName = computed(() => (page.props.tenant as { name?: string } | null)?.name ?? '');

const intlLocale = computed(() => (locale.value === 'ur' ? 'ur-PK' : 'en-GB'));

function formatReportDate(iso: string): string {
    const [y, m, d] = iso.split('-').map((n) => Number.parseInt(n, 10));
    if (!y || !m || !d) {
        return iso;
    }
    const dt = new Date(y, m - 1, d);
    return new Intl.DateTimeFormat(intlLocale.value, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(dt);
}

const printedAt = computed(() =>
    new Intl.DateTimeFormat(intlLocale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date()),
);

const locationSummary = computed(() => {
    const c = props.filterLabels.city?.trim();
    const a = props.filterLabels.area?.trim();
    if (c && a) {
        return t('reports.filterCityAreaValue', { city: c, area: a });
    }
    if (c) {
        return c;
    }
    return t('reports.filterAllLocations');
});

const hasDateRange = computed(() => Boolean(props.filterLabels.from && props.filterLabels.to));

const hasSearch = computed(() => Boolean(props.filterLabels.q?.trim()));

function printReport() {
    window.print();
}
</script>

<template>
    <div class="rounded-lg border border-border bg-muted/25 p-4 text-sm text-foreground print:border-foreground/30 print:bg-transparent">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1 space-y-2">
                <p v-if="tenantName" class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {{ t('reports.printBusiness') }}:
                    <span class="font-medium normal-case tracking-normal text-foreground">{{ tenantName }}</span>
                </p>
                <div class="space-y-2">
                    <div class="flex flex-wrap gap-x-2 gap-y-1">
                        <span class="shrink-0 text-muted-foreground">{{ t('reports.filterLocationHeading') }}</span>
                        <span class="font-medium">{{ locationSummary }}</span>
                    </div>
                    <div v-if="hasDateRange" class="flex flex-wrap gap-x-2 gap-y-1">
                        <span class="shrink-0 text-muted-foreground">{{ t('reports.filterPeriodHeading') }}</span>
                        <span class="font-medium">
                            {{ formatReportDate(filterLabels.from!) }} — {{ formatReportDate(filterLabels.to!) }}
                        </span>
                    </div>
                    <div v-if="hasSearch" class="flex flex-wrap gap-x-2 gap-y-1">
                        <span class="shrink-0 text-muted-foreground">{{ t('reports.filterSearchHeading') }}</span>
                        <span class="break-words font-medium">{{ filterLabels.q }}</span>
                    </div>
                </div>
                <p class="hidden text-xs text-muted-foreground print:block">{{ t('reports.printedAt') }} {{ printedAt }}</p>
            </div>
            <Button type="button" variant="outline" class="shrink-0 gap-2 print:hidden" @click="printReport">
                <Printer class="size-4 shrink-0" aria-hidden="true" />
                {{ t('reports.print') }}
            </Button>
        </div>
    </div>
</template>
