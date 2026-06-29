export const paymentBadge: Record<string, string> = {
    cash:      'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
    jazzcash:  'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400',
    easypaisa: 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-400',
    udhaar:    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
    mixed:     'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
};

export const statusBadge: Record<string, string> = {
    completed:          'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
    voided:             'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
    pending:            'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400',
    partially_returned: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400',
    returned:           'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
    draft:              'bg-muted text-muted-foreground',
    cancelled:          'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
    received:           'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
    partially_received: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400',
};

export const saleStatusVariant: Record<string, 'success' | 'destructive' | 'warning' | 'info' | 'purple' | 'secondary' | 'outline'> = {
    completed:          'success',
    voided:             'destructive',
    pending:            'warning',
    partially_returned: 'warning',
    returned:           'info',
};

/** Badge variant union for journals — matches `Badge.vue` */
export type JournalRefBadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline' | 'success' | 'warning' | 'info' | 'purple';

/** Journal entry reference_type → i18n label + Badge variant (General Ledger) */
export const journalReferenceBadge: Record<string, { labelKey: string; variant: JournalRefBadgeVariant }> = {
    manual: { labelKey: 'accounts.refManual', variant: 'secondary' },
    sale: { labelKey: 'badges.sale', variant: 'info' },
    payment: { labelKey: 'badges.payment', variant: 'success' },
    void: { labelKey: 'badges.void', variant: 'destructive' },
    expense: { labelKey: 'accounts.refExpense', variant: 'warning' },
    return: { labelKey: 'badges.glReturn', variant: 'purple' },
    expense_void: { labelKey: 'badges.glExpenseVoid', variant: 'destructive' },
    po_payment: { labelKey: 'badges.glPoPayment', variant: 'success' },
    po_payment_void: { labelKey: 'badges.glPoPaymentVoid', variant: 'destructive' },
    customer_payment_void: { labelKey: 'badges.glCustomerPaymentVoid', variant: 'destructive' },
    purchase: { labelKey: 'badges.glPurchase', variant: 'info' },
    supplier_return: { labelKey: 'badges.glSupplierReturn', variant: 'purple' },
};

export function formatJournalReferenceFallback(raw: string): string {
    return raw
        .split('_')
        .filter(Boolean)
        .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1).toLowerCase())
        .join(' ');
}

export const ledgerTypeBadge: Record<string, { labelKey: string; class: string }> = {
    sale:           { labelKey: 'badges.sale',         class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
    payment:        { labelKey: 'badges.payment',      class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' },
    payment_void:   { labelKey: 'badges.pmtVoided',    class: 'bg-muted text-muted-foreground line-through' },
    payment_voided: { labelKey: 'badges.voided',       class: 'bg-muted text-muted-foreground opacity-60' },
    void:           { labelKey: 'badges.void',         class: 'bg-muted text-muted-foreground' },
    opening:        { labelKey: 'badges.opening',      class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
    charge:         { labelKey: 'badges.charge',       class: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' },
    writeoff:       { labelKey: 'badges.writeoff',     class: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' },
};
