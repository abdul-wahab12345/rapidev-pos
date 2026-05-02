export const paymentBadge: Record<string, string> = {
    cash:      'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
    jazzcash:  'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400',
    easypaisa: 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-400',
    udhaar:    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
    mixed:     'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
};

export const statusBadge: Record<string, string> = {
    completed: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
    voided:    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
    pending:   'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400',
};

export const ledgerTypeBadge: Record<string, { label: string; class: string }> = {
    sale:         { label: 'Sale',         class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
    payment:      { label: 'Payment',      class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' },
    payment_void:   { label: 'Pmt Voided',  class: 'bg-muted text-muted-foreground line-through' },
    payment_voided: { label: 'Voided',       class: 'bg-muted text-muted-foreground opacity-60' },
    void:         { label: 'Void',         class: 'bg-muted text-muted-foreground' },
};
