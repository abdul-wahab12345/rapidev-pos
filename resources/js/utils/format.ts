export function formatMoney(n: number): string {
    const rounded = Math.round(n);
    // Manual formatter — avoids locale inconsistencies across environments.
    // Uses comma as thousands separator, no decimals (amounts are in whole PKR).
    return 'Rs ' + rounded.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

export function formatDateTime(dt: string, includeWeekday = false): string {
    return new Date(dt).toLocaleString('en-PK', {
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
