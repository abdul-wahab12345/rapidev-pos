<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    BadgeDollarSign,
    Building2,
    Globe,
    Printer,
    Receipt,
    Save,
    Upload,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: t('settings.businessTitle'), href: route('business-settings.index') },
]);

const planBadge = computed<Record<string, { label: string; cls: string }>>(() => ({
    trial:    { label: t('settings.planTrial'),    cls: 'bg-amber-500/10 text-amber-600 border-amber-500/20' },
    basic:    { label: t('settings.planBasic'),    cls: 'bg-blue-500/10 text-blue-600 border-blue-500/20' },
    standard: { label: t('settings.planStandard'), cls: 'bg-violet-500/10 text-violet-600 border-violet-500/20' },
    pro:      { label: t('settings.planPro'),      cls: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' },
}));

const props = defineProps<{
    settings: Record<string, any>;
    tenant_name: string;
    plan: string;
}>();

const page = usePage();
const flash = (page.props.flash ?? {}) as { success?: string; error?: string };

const form = useForm({
    business_name:    props.settings.business_name    ?? '',
    business_phone:   props.settings.business_phone   ?? '',
    business_email:   props.settings.business_email   ?? '',
    business_address: props.settings.business_address ?? '',
    business_city:    props.settings.business_city    ?? '',
    currency:         props.settings.currency         ?? 'PKR',
    currency_symbol:  props.settings.currency_symbol  ?? 'Rs',
    language:         props.settings.language         ?? 'en',
    tax_enabled:      props.settings.tax_enabled      ?? false,
    tax_name:         props.settings.tax_name         ?? 'GST',
    tax_rate:         props.settings.tax_rate         ?? 17,
    receipt_header:   props.settings.receipt_header   ?? '',
    receipt_footer:   props.settings.receipt_footer   ?? 'Thank you for your business!',
    receipt_show_tax: props.settings.receipt_show_tax ?? true,
    receipt_show_logo:props.settings.receipt_show_logo?? true,
});

function save() {
    form.post(route('business-settings.update'));
}

// Logo upload
const logoInput    = ref<HTMLInputElement | null>(null);
const logoPreview  = ref<string>(props.settings.logo_url ?? '');
const uploading    = ref(false);
const uploadError  = ref('');
const uploadDone   = ref(false);

function pickLogo() {
    logoInput.value?.click();
}

async function uploadLogo(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;

    uploadError.value = '';
    uploadDone.value  = false;

    // Show local preview immediately while uploading
    const reader = new FileReader();
    reader.onload = ev => { logoPreview.value = ev.target?.result as string; };
    reader.readAsDataURL(file);

    const fd = new FormData();
    fd.append('logo', file);
    uploading.value = true;

    try {
        const { data } = await axios.post(route('business-settings.upload-logo'), fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        // Switch preview to the actual stored server URL
        logoPreview.value = data.logo_url;
        uploadDone.value  = true;
    } catch (err: any) {
        uploadError.value = err?.response?.data?.message ?? t('settings.logoUploadFailed');
        logoPreview.value = props.settings.logo_url ?? '';
    } finally {
        uploading.value = false;
        if (logoInput.value) logoInput.value.value = '';
    }
}

</script>

<template>
    <Head :title="t('settings.businessTitle')" />
    <AppLayout :breadcrumbs="breadcrumbs">

        <div class="max-w-3xl mx-auto px-4 py-6 sm:px-6 space-y-6">

            <!-- Page header -->
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">{{ t('settings.businessTitle') }}</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">{{ t('settings.businessSubtitle') }}</p>
                </div>
                <span
                    :class="['inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-medium capitalize', (planBadge[plan] ?? planBadge.trial).cls]"
                >
                    {{ (planBadge[plan] ?? planBadge.trial).label }}
                </span>
            </div>

            <!-- Success flash -->
            <div
                v-if="flash.success"
                class="flex items-center gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-400"
            >
                <Save class="h-4 w-4 shrink-0" />
                {{ flash.success }}
            </div>

            <!-- ── Business Info ───────────────────────────────── -->
            <section class="rounded-xl border border-border bg-card p-6 space-y-5">
                <div class="flex items-center gap-2.5 border-b border-border pb-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Building2 class="h-4.5 w-4.5" />
                    </div>
                    <h2 class="font-semibold text-foreground">{{ t('settings.businessInfo') }}</h2>
                </div>

                <!-- Logo -->
                <div class="flex items-center gap-4">
                    <div class="h-20 w-20 rounded-xl border border-border bg-muted flex items-center justify-center overflow-hidden">
                        <img v-if="logoPreview" :src="logoPreview" :alt="t('settings.logo')" class="h-full w-full object-contain" />
                        <Building2 v-else class="h-8 w-8 text-muted-foreground" />
                    </div>
                    <div class="space-y-1">
                        <p class="text-sm font-medium text-foreground">{{ t('settings.logo') }}</p>
                        <p class="text-xs text-muted-foreground">{{ t('settings.logoHint') }}</p>
                        <Button variant="outline" size="sm" @click="pickLogo" :disabled="uploading" class="gap-1.5 text-xs">
                            <Upload class="h-3.5 w-3.5" />
                            {{ uploading ? t('settings.uploadingLogo') : t('settings.uploadLogo') }}
                        </Button>
                        <input ref="logoInput" type="file" accept="image/*" class="hidden" @change="uploadLogo" />
                        <p v-if="uploadDone" class="text-xs text-emerald-600 dark:text-emerald-400">
                            {{ t('settings.logoSavedSuccess') }}
                        </p>
                        <p v-if="uploadError" class="text-xs text-destructive">{{ uploadError }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="bname">{{ t('settings.businessName') }} <span class="text-destructive">*</span></Label>
                        <Input id="bname" v-model="form.business_name" :placeholder="t('settings.businessNamePlaceholder')" />
                        <p v-if="form.errors.business_name" class="text-xs text-destructive">{{ form.errors.business_name }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="phone">{{ t('common.phone') }}</Label>
                        <Input id="phone" v-model="form.business_phone" :placeholder="t('settings.businessPhonePlaceholder')" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="email">{{ t('common.email') }}</Label>
                        <Input id="email" v-model="form.business_email" type="email" :placeholder="t('settings.businessEmailPlaceholder')" />
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="address">{{ t('common.address') }}</Label>
                        <Input id="address" v-model="form.business_address" :placeholder="t('settings.businessAddressPlaceholder')" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="city">{{ t('common.city') }}</Label>
                        <Input id="city" v-model="form.business_city" :placeholder="t('settings.businessCityPlaceholder')" />
                    </div>
                </div>
            </section>

            <!-- ── Language & Currency ─────────────────────────── -->
            <section class="rounded-xl border border-border bg-card p-6 space-y-5">
                <div class="flex items-center gap-2.5 border-b border-border pb-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Globe class="h-4.5 w-4.5" />
                    </div>
                    <h2 class="font-semibold text-foreground">{{ t('settings.languageCurrency') }}</h2>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label>{{ t('settings.languageLabel') }}</Label>
                        <Select v-model="form.language">
                            <SelectTrigger>
                                <SelectValue :placeholder="t('settings.selectLanguage')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="en">{{ t('settings.languageEnglish') }}</SelectItem>
                                <SelectItem value="ur">{{ t('layout.languageUrdu') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1.5">
                        <Label>{{ t('settings.currencyCode') }}</Label>
                        <Select v-model="form.currency">
                            <SelectTrigger>
                                <SelectValue :placeholder="t('settings.selectCurrency')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="PKR">{{ t('settings.currencyPkr') }}</SelectItem>
                                <SelectItem value="USD">{{ t('settings.currencyUsd') }}</SelectItem>
                                <SelectItem value="AED">{{ t('settings.currencyAed') }}</SelectItem>
                                <SelectItem value="SAR">{{ t('settings.currencySar') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1.5">
                        <Label>{{ t('settings.currencySymbol') }}</Label>
                        <Input v-model="form.currency_symbol" placeholder="Rs" class="max-w-24" />
                        <p class="text-xs text-muted-foreground">{{ t('settings.currencySymbolHint', { symbol: form.currency_symbol || 'Rs' }) }}</p>
                    </div>
                </div>
            </section>

            <!-- ── Tax ────────────────────────────────────────── -->
            <section class="rounded-xl border border-border bg-card p-6 space-y-5">
                <div class="flex items-center gap-2.5 border-b border-border pb-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <BadgeDollarSign class="h-4.5 w-4.5" />
                    </div>
                    <h2 class="font-semibold text-foreground">{{ t('settings.taxSection') }}</h2>
                </div>

                <div class="flex items-center justify-between rounded-lg border border-border p-4 gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-foreground">{{ t('settings.enableTax') }}</p>
                        <p class="text-xs text-muted-foreground">{{ t('settings.taxEnableSubtitle') }}</p>
                    </div>
                    <div class="shrink-0 self-center" dir="ltr">
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.tax_enabled"
                            :aria-label="t('settings.enableTax')"
                            @click="form.tax_enabled = !form.tax_enabled"
                            :class="[
                                'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                                form.tax_enabled ? 'bg-primary' : 'bg-muted-foreground/30',
                            ]"
                        >
                            <span
                                :class="[
                                    'pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition-transform',
                                    form.tax_enabled ? 'translate-x-5' : 'translate-x-1',
                                ]"
                            />
                        </button>
                    </div>
                </div>

                <div v-if="form.tax_enabled" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label>{{ t('settings.taxNameLabel') }}</Label>
                        <Input v-model="form.tax_name" :placeholder="t('settings.taxNamePlaceholder')" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>{{ t('settings.taxRateLabel') }}</Label>
                        <Input v-model.number="form.tax_rate" type="number" min="0" max="100" step="0.5" />
                    </div>
                </div>
            </section>

            <!-- ── Receipt ────────────────────────────────────── -->
            <section class="rounded-xl border border-border bg-card p-6 space-y-5">
                <div class="flex items-center gap-2.5 border-b border-border pb-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Receipt class="h-4.5 w-4.5" />
                    </div>
                    <h2 class="font-semibold text-foreground">{{ t('settings.receiptSection') }}</h2>
                </div>

                <div class="grid grid-cols-1 gap-4">
                    <div class="space-y-1.5">
                        <Label>{{ t('settings.receiptHeader') }}</Label>
                        <Input v-model="form.receipt_header" :placeholder="t('settings.receiptHeaderPlaceholder')" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>{{ t('settings.receiptFooter') }}</Label>
                        <Input v-model="form.receipt_footer" :placeholder="t('settings.receiptFooterPlaceholder')" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="flex items-center gap-3 cursor-pointer rounded-lg border border-border p-3 hover:bg-muted/40 transition-colors">
                        <input type="checkbox" v-model="form.receipt_show_logo" class="h-4 w-4 rounded border-border accent-primary" />
                        <span class="text-sm text-foreground">{{ t('settings.showLogo') }}</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer rounded-lg border border-border p-3 hover:bg-muted/40 transition-colors">
                        <input type="checkbox" v-model="form.receipt_show_tax" class="h-4 w-4 rounded border-border accent-primary" />
                        <span class="text-sm text-foreground">{{ t('settings.showTax') }}</span>
                    </label>
                </div>

                <!-- Receipt Preview -->
                <div class="rounded-xl border border-border bg-muted/30 p-4">
                    <p class="mb-3 text-xs font-medium uppercase tracking-wide text-muted-foreground flex items-center gap-1.5">
                        <Printer class="h-3.5 w-3.5" />
                        {{ t('settings.receiptPreview80mm') }}
                    </p>
                    <div class="mx-auto max-w-[320px] rounded-lg bg-white dark:bg-zinc-900 p-4 font-mono text-[11px] text-zinc-800 dark:text-zinc-200 shadow-sm border border-border">
                        <div v-if="form.receipt_show_logo && logoPreview" class="mb-2 flex justify-center">
                            <img :src="logoPreview" :alt="t('settings.logo')" class="h-12 object-contain" />
                        </div>
                        <div class="text-center font-bold text-sm">{{ form.business_name || tenant_name }}</div>
                        <div v-if="form.business_phone" class="text-center text-[10px] text-zinc-500">{{ form.business_phone }}</div>
                        <div v-if="form.business_address" class="text-center text-[10px] text-zinc-500">{{ form.business_address }}<span v-if="form.business_city">, {{ form.business_city }}</span></div>
                        <div v-if="form.receipt_header" class="mt-2 text-center text-[10px] text-zinc-500 italic">{{ form.receipt_header }}</div>
                        <div class="my-2 border-t border-dashed border-zinc-300 dark:border-zinc-700"></div>
                        <div class="flex justify-between"><span>{{ t('settings.receiptDemoItem1') }}</span><span>{{ t('settings.receiptDemoItemLine1') }}</span></div>
                        <div class="flex justify-between"><span>{{ t('settings.receiptDemoItem2') }}</span><span>{{ t('settings.receiptDemoItemLine2') }}</span></div>
                        <div class="my-2 border-t border-dashed border-zinc-300 dark:border-zinc-700"></div>
                        <div class="flex justify-between"><span>{{ t('receipt.subtotal') }}</span><span>Rs 600</span></div>
                        <div v-if="form.tax_enabled && form.receipt_show_tax" class="flex justify-between text-zinc-500">
                            <span>{{ form.tax_name }} ({{ form.tax_rate }}%)</span><span>Rs {{ Math.round(600 * (form.tax_rate / 100)) }}</span>
                        </div>
                        <div class="flex justify-between font-bold"><span>{{ t('receipt.grandTotal') }}</span><span>Rs {{ form.tax_enabled && form.receipt_show_tax ? Math.round(600 * (1 + form.tax_rate / 100)) : 600 }}</span></div>
                        <div class="my-2 border-t border-dashed border-zinc-300 dark:border-zinc-700"></div>
                        <div v-if="form.receipt_footer" class="text-center text-[10px] text-zinc-500">{{ form.receipt_footer }}</div>
                    </div>
                </div>
            </section>

            <!-- Save -->
            <div class="flex justify-end">
                <Button @click="save" :disabled="form.processing" size="lg" class="gap-2 min-w-32">
                    <Save class="h-4 w-4" />
                    {{ form.processing ? t('settings.savingSettingsDots') : t('settings.saveSettings') }}
                </Button>
            </div>

        </div>
    </AppLayout>
</template>
