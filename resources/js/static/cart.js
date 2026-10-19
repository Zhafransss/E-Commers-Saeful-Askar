/**
 * Static-phase cart kept in localStorage, so the product page, the cart page, and
 * the header badge share one cart that survives a refresh.
 *
 * Like `cart_items` in docs/erd.md, only rows of { id, variant_id, qty } are stored.
 * Price, stock, weight, and names are read from window.staticVariants on every load,
 * the way the backend reads product_variants, so a price or stock change shows up in
 * the cart. load() returns the same Cart object as CART-01 (docs/api/cart.md).
 *
 * Enabled when the layout provides window.staticCartSeed. Once the backend cart
 * endpoints are live, remove the seed, window.staticVariants, and this module.
 */
const STORAGE_KEY = 'saefulaskar.static-cart';

export const enabled = () => window.staticCartSeed !== undefined;

const lookup = (variantId) => window.staticVariants?.[variantId] ?? null;

/** Stored rows; also converts the earlier format that kept whole variants. */
function readRows() {
    try {
        const saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY));
        const rows = Array.isArray(saved) ? saved : saved?.items;
        if (rows) return rows.map((row) => ({ id: row.id, variant_id: row.variant_id ?? row.variant?.id, qty: row.qty }));
    } catch {
        // Private mode or corrupt data: fall back to the seed.
    }
    return structuredClone(window.staticCartSeed ?? []);
}

/** Builds the CART-01 object from stored rows. Rows whose variant is unknown are dropped. */
export function load() {
    const items = readRows()
        .map((row) => {
            const entry = lookup(row.variant_id);
            if (!entry) return null;
            return { id: row.id, variant: structuredClone(entry.variant), product: structuredClone(entry.product), qty: row.qty, subtotal: 0, weight_total: 0, issue: null };
        })
        .filter(Boolean);

    return recompute({ items, summary: {} });
}

/** Persists only { id, variant_id, qty } per row. */
export function save(cart) {
    try {
        const rows = cart.items.map((item) => ({ id: item.id, variant_id: item.variant.id, qty: item.qty }));
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(rows));
    } catch {
        // Storage unavailable: the cart still works until the page is left.
    }
    return cart;
}

/** Back to the sample cart (handy while testing: `staticCart.reset()` in the console). */
export function reset() {
    try {
        window.localStorage.removeItem(STORAGE_KEY);
    } catch {
        // Nothing stored.
    }
    return load();
}

/** order_items.unit for a variant (the product's sell_unit). */
export const unitOf = (variantId) => lookup(variantId)?.unit ?? 'Unit';

/** Recalculates issues, line totals, and the summary the way the backend does (CART-01). */
export function recompute(cart) {
    for (const item of cart.items) {
        if (item.issue !== 'PRODUCT_INACTIVE') {
            item.issue = !item.variant.in_stock || item.variant.available_qty <= 0
                ? 'OUT_OF_STOCK'
                : item.qty > item.variant.available_qty ? 'QTY_EXCEEDS_STOCK' : null;
        }
        item.subtotal = item.variant.price * item.qty;
        // package_weight may be null in the ERD; the API resolves the product fallback (INT-JB-05).
        item.weight_total = (item.variant.package_weight ?? 0) * item.qty;
    }

    const items = cart.items;
    cart.summary = {
        item_count: items.length,
        total_qty: items.reduce((sum, item) => sum + item.qty, 0),
        subtotal: items.filter((item) => item.issue !== 'PRODUCT_INACTIVE').reduce((sum, item) => sum + item.subtotal, 0),
        total_weight: items.reduce((sum, item) => sum + item.weight_total, 0),
        has_issue: items.some((item) => item.issue !== null),
    };
    return cart;
}

/** CART-02: adds qty of a variant, merging with an existing row for the same variant (UNIQUE user + variant). */
export function add(variantId, qty) {
    const rows = readRows();
    const row = rows.find((r) => r.variant_id === variantId);

    if (row) {
        row.qty += qty;
    } else {
        rows.push({ id: Math.max(0, ...rows.map((r) => r.id)) + 1, variant_id: variantId, qty });
    }

    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(rows));
    } catch {
        // Storage unavailable.
    }
    return load();
}
