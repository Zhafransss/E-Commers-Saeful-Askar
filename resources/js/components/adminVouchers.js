import { api, ApiError } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';
import { listState } from './adminList.js';

// The server reads the times as shop time (Asia/Jakarta), whatever zone the admin's browser is in.
const SHOP_ZONE = 'Asia/Jakarta';

/** ISO time → "YYYY-MM-DDTHH:mm" in shop time, the value an <input type="datetime-local"> takes. */
function toInput(value) {
    if (!value) return '';
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-CA', { timeZone: SHOP_ZONE, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
            .formatToParts(new Date(value))
            .map((p) => [p.type, p.value]),
    );
    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}

const emptyForm = () => ({
    name: '',
    type: 'free_shipping',
    amount: '',
    min_subtotal: '0',
    starts_at: toInput(new Date().toISOString()),
    ends_at: '',
    quota: '',
    per_user_limit: '',
});

/**
 * Alpine.data('adminVouchers'): /admin/vouchers (docs/api/admin.md ADM-30 list, ADM-31 create, ADM-32 update,
 * ADM-33 delete). Shipping vouchers only lower the shipping cost; the active ones are offered on the checkout.
 */
export default () => ({
    ...listState(endpoints.admin.vouchers, { q: '' }),

    // null = form closed, 0 = adding, otherwise the id being edited
    editing: null,
    form: emptyForm(),
    fieldErrors: {},
    formError: null,
    saving: false,

    removing: null,
    removeError: null,
    removeBusy: false,

    notice: null,

    openCreate() {
        this.form = emptyForm();
        this.fieldErrors = {};
        this.formError = null;
        this.editing = 0;
    },

    openEdit(voucher) {
        this.form = {
            name: voucher.name,
            type: voucher.type,
            amount: voucher.amount === null ? '' : String(voucher.amount),
            min_subtotal: String(voucher.min_subtotal),
            starts_at: toInput(voucher.starts_at),
            ends_at: toInput(voucher.ends_at),
            quota: voucher.quota === null ? '' : String(voucher.quota),
            per_user_limit: voucher.per_user_limit === null ? '' : String(voucher.per_user_limit),
        };
        this.fieldErrors = {};
        this.formError = null;
        this.editing = voucher.id;
    },

    closeForm() {
        this.editing = null;
    },

    async save() {
        this.saving = true;
        this.fieldErrors = {};
        this.formError = null;
        try {
            const creating = this.editing === 0;
            const body = { ...this.form, amount: this.form.type === 'free_shipping' ? null : this.form.amount };
            if (creating) {
                await api.post(endpoints.admin.vouchers, body);
            } else {
                await api.put(endpoints.admin.voucher(this.editing), body);
            }
            this.notice = creating ? 'Voucher ditambahkan.' : 'Perubahan disimpan. Pesanan yang sudah dibuat tidak ikut berubah.';
            this.editing = null;
            await this.fetchPage(creating ? 1 : this.meta.current_page);
        } catch (error) {
            if (error instanceof ApiError && error.status === 422) {
                this.fieldErrors = Object.fromEntries(Object.entries(error.errors).map(([field, messages]) => [field, messages[0]]));
            } else {
                this.formError = error.message;
            }
        } finally {
            this.saving = false;
        }
    },

    askRemove(voucher) {
        this.removing = voucher;
        this.removeError = null;
    },

    async remove() {
        this.removeBusy = true;
        this.removeError = null;
        try {
            await api.delete(endpoints.admin.voucher(this.removing.id));
            this.notice = `${this.removing.name} dihapus.`;
            this.removing = null;
            await this.fetchPage(this.items.length === 1 && this.meta.current_page > 1 ? this.meta.current_page - 1 : this.meta.current_page);
        } catch (error) {
            this.removeError = error.message;
        } finally {
            this.removeBusy = false;
        }
    },

    /** "Gratis ongkir" or "Potongan ongkir Rp5.000". */
    benefit(voucher) {
        return voucher.type === 'free_shipping' ? 'Gratis ongkir' : `Potongan ongkir ${this.$format.formatRupiah(voucher.amount)}`;
    },

    period(voucher) {
        return `${this.date(voucher.starts_at)} – ${voucher.ends_at ? this.date(voucher.ends_at) : 'tanpa batas'}`;
    },

    usage(voucher) {
        return voucher.quota === null ? `${voucher.used_count} terpakai` : `${voucher.used_count} / ${voucher.quota}`;
    },

    statusBadge(status) {
        return (
            {
                running: 'bg-green-100 text-green-700',
                scheduled: 'bg-blue-100 text-blue-700',
                exhausted: 'bg-yellow-100 text-yellow-800',
                ended: 'bg-gray-100 text-gray-600',
            }[status] ?? 'bg-gray-100 text-gray-600'
        );
    },
});
