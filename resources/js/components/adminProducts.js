import { api } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';
import { listState } from './adminList.js';

const KINDS = [
    { type: 'categories', label: 'Kategori', hint: 'Salin kategori dari Jubelio.' },
    { type: 'products', label: 'Produk', hint: 'Hanya produk yang berubah sejak sinkron terakhir.' },
    { type: 'stock', label: 'Stok', hint: 'Samakan stok semua varian dengan Jubelio.' },
    { type: 'full', label: 'Sinkron penuh', hint: 'Kategori, lalu semua produk (nonaktifkan yang hilang), lalu stok.' },
];

/**
 * Alpine.data('adminProducts'): /admin/products (docs/api/admin.md ADM-20 list, ADM-13 start a sync). The
 * products and their variants are only listed (Jubelio is the source); the sync cards that start a sync from
 * Jubelio live here, and what each sync did is in the history on /admin/sync.
 */
export default () => ({
    ...listState(endpoints.admin.products, { q: '', status: '' }),

    kinds: KINDS,
    starting: null,
    notice: null,
    actionError: null,
    expanded: {},

    toggle(id) {
        this.expanded[id] = !this.expanded[id];
    },

    // ADM-13
    async start(type, full = false) {
        this.starting = type;
        this.notice = null;
        this.actionError = null;
        try {
            const { message } = await api.post(endpoints.admin.sync, type === 'products' && full ? { type, full: true } : { type });
            this.notice = message;
        } catch (error) {
            this.actionError = error.message;
        } finally {
            this.starting = null;
        }
    },

    stockLabel(qty) {
        return qty > 0 ? `${qty}` : 'Habis';
    },
});
