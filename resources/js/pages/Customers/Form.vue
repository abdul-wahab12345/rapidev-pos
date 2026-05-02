<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';

interface CustomerData {
    id?: string;
    name: string;
    phone: string | null;
    cnic: string | null;
    address: string | null;
    notes: string | null;
    credit_limit: number;
    discount_percent: number;
}

const props = defineProps<{ customer: CustomerData | null }>();

const isEdit = !!props.customer?.id;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customers', href: '/customers' },
    { title: isEdit ? 'Edit Customer' : 'Add Customer', href: '#' },
];

const form = useForm({
    name:             props.customer?.name             ?? '',
    phone:            props.customer?.phone            ?? '',
    cnic:             props.customer?.cnic             ?? '',
    address:          props.customer?.address          ?? '',
    notes:            props.customer?.notes            ?? '',
    credit_limit:     props.customer?.credit_limit     ?? '',
    discount_percent: props.customer?.discount_percent ?? '',
});

function submit() {
    if (isEdit && props.customer?.id) {
        form.put(route('customers.update', props.customer.id));
    } else {
        form.post(route('customers.store'));
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Customer' : 'Add Customer'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-xl">

            <!-- Header -->
            <div class="flex items-center gap-3">
                <Link
                    :href="isEdit ? route('customers.show', customer!.id) : route('customers.index')"
                    class="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
                    <ArrowLeft class="h-4 w-4" /> Back
                </Link>
                <h1 class="text-xl font-bold tracking-tight text-foreground">
                    {{ isEdit ? 'Edit Customer' : 'Add New Customer' }}
                </h1>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-4 rounded-xl border border-border bg-card p-6">

                <!-- Name -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-foreground">
                        Name <span class="text-destructive">*</span>
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        placeholder="Customer full name"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                        :class="form.errors.name ? 'border-destructive' : ''"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                </div>

                <!-- Phone -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-foreground">
                        Phone <span class="text-destructive">*</span>
                    </label>
                    <input
                        v-model="form.phone"
                        type="text"
                        placeholder="03xx-xxxxxxx"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                        :class="form.errors.phone ? 'border-destructive' : ''"
                    />
                    <p v-if="form.errors.phone" class="mt-1 text-xs text-destructive">{{ form.errors.phone }}</p>
                </div>

                <!-- CNIC -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-foreground">CNIC</label>
                    <input
                        v-model="form.cnic"
                        type="text"
                        placeholder="xxxxx-xxxxxxx-x"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>

                <!-- Address -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-foreground">Address</label>
                    <input
                        v-model="form.address"
                        type="text"
                        placeholder="Street, area, city"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>

                <!-- Credit limit + Discount % (side by side) -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-foreground">
                            Credit Limit (Rs)
                            <span class="ml-1 text-xs font-normal text-muted-foreground">0 = no limit</span>
                        </label>
                        <input
                            v-model="form.credit_limit"
                            type="number"
                            min="0"
                            placeholder="0"
                            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-foreground">
                            Auto Discount
                            <span class="ml-1 text-xs font-normal text-muted-foreground">% on every sale</span>
                        </label>
                        <div class="relative">
                            <input
                                v-model="form.discount_percent"
                                type="number"
                                min="0"
                                max="100"
                                step="0.5"
                                placeholder="0"
                                class="w-full rounded-lg border border-input bg-background px-3 py-2 pr-7 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
                                :class="form.errors.discount_percent ? 'border-destructive' : ''"
                            />
                            <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-muted-foreground">%</span>
                        </div>
                        <p v-if="form.errors.discount_percent" class="mt-1 text-xs text-destructive">{{ form.errors.discount_percent }}</p>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-foreground">Notes</label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        placeholder="Any notes about this customer…"
                        class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                    />
                </div>

                <!-- Actions -->
                <div class="flex gap-2 pt-2">
                    <Link
                        :href="isEdit ? route('customers.show', customer!.id) : route('customers.index')"
                        class="flex-1 rounded-xl border border-border py-2.5 text-center text-sm text-muted-foreground hover:bg-accent transition-colors"
                    >Cancel</Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-2.5 text-sm font-bold text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-60"
                    >
                        <Save class="h-4 w-4" />
                        {{ form.processing ? 'Saving…' : (isEdit ? 'Save Changes' : 'Add Customer') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
