export function formatMoney(n: number): string {
    const rounded = Math.round(n);
    // Manual formatter — avoids locale inconsistencies across environments.
    // Uses comma as thousands separator, no decimals (amounts are in whole PKR).
    return 'Rs ' + rounded.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

export function formatDateTime(dt: string, includeWeekday = false, locale: string = 'en-PK'): string {
    return new Date(dt).toLocaleString(locale, {
        ...(includeWeekday ? { weekday: 'short' as const } : {}),
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export const isUrdu = (text: string): boolean =>
    /[\u0600-\u06FF\u0750-\u077F]/.test(text);

const UNIT_LABELS: Record<string, string> = {
    sq_m:   'm\u00B2',
    sq_ft:  'ft\u00B2',
    piece:  'pcs',
    pcs:    'pcs',
    kg:     'kg',
    gram:   'g',
    liter:  'L',
    meter:  'm',
    dozen:  'doz',
    box:    'box',
    bag:    'bag',
    ton:    'ton',
};

export function formatUnit(unit: string | null | undefined): string {
    if (!unit) return '';
    return UNIT_LABELS[unit] ?? unit;
}
