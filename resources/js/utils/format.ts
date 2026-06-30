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

// Units that are always whole numbers — no decimal display needed
const WHOLE_UNITS = new Set(['piece', 'pcs', 'box', 'bag', 'dozen', 'ton']);

export function formatQty(quantity: number | string, unit: string | null | undefined): string {
    const n = Number(quantity);
    if (WHOLE_UNITS.has(unit ?? '')) return Math.round(n).toString();
    // Decimal units: show up to 2 decimal places, trimming trailing zeros
    return parseFloat(n.toFixed(2)).toString();
}

const TILE_MATERIALS = new Set(['tile', 'ceramic', 'mosaic', 'border']);

/** "≈3 box + 2 tile" from an m² quantity, or null for non-tile products. */
export function tileBreakdown(
    qtyM2: number | string,
    p: { tiles_per_box?: number | null; sq_m_per_box?: number | null; material_type?: string | null },
): string | null {
    if (!p.tiles_per_box || !p.sq_m_per_box) return null;
    if (!TILE_MATERIALS.has(p.material_type ?? '')) return null;
    const sqmPerTile = p.sq_m_per_box / p.tiles_per_box;
    if (!sqmPerTile) return null;
    const totalTiles = Math.round(Math.abs(Number(qtyM2)) / sqmPerTile);
    if (totalTiles <= 0) return null;
    const boxes = Math.floor(totalTiles / p.tiles_per_box);
    const loose = totalTiles % p.tiles_per_box;
    if (boxes === 0 && loose === 0) return null;
    return loose > 0 ? `≈${boxes} box + ${loose} tile` : `≈${boxes} box`;
}
