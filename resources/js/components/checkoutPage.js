import { api, ApiError } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';

/**
 * Alpine.data('checkoutPage'): the /checkout page (docs/api/checkout-orders.md ORD-01,
 * shipping.md SHIP-01, addresses-regions.md ADDR-01/02 and REG-01 … REG-04, cart.md CART-01).
 *
 * The cart lives in Alpine.store('cart'). Addresses, regions, shipping rates, and the order
 * all come from the API; this component only holds the page state. Which prices and totals
 * count is always the server's: `expected_grand_total` only lets ORD-01 notice that
 * something changed since the customer looked (PRICE_CHANGED).
 */
const RATE_TTL_FALLBACK_MS = 30 * 60 * 1000;

const SERVICE_TYPES = { instant: 'Instan', same_day: 'Same day', regular: 'Reguler', cargo: 'Kargo' };

const emptyForm = () => ({
    label: '',
    recipient_name: '',
    phone: '',
    address_line: '',
    province_id: '',
    city_id: '',
    district_id: '',
    subdistrict_id: '',
    post_code: '',
    is_default: false,
});

const EMPTY_CART = { items: [], summary: { item_count: 0, total_qty: 0, subtotal: 0, total_weight: 0, has_issue: false } };

/** RFC 4122 v4. crypto.randomUUID() only exists on HTTPS and localhost. */
function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export default () => ({
    loaded: false,
    loadError: null,

    addresses: [],
    addressId: null,
    formOpen: false,
    form: emptyForm(),
    formErrors: {},
    formMessage: null,
    saving: false,

    // Region choices, loaded one level at a time (REG-01 … REG-04).
    provinces: [],
    cities: [],
    districts: [],
    subdistricts: [],
    regionLoading: null,

    rates: [],
    ratesLoading: false,
    ratesError: null,
    ratesExpireAt: null,
    rateId: null,

    note: '',
    paying: false,
    payError: null,
    // One Idempotency-Key per distinct request, reused if the same request is sent again.
    payKey: null,
    payKeyBody: null,

    async init() {
        this.$watch('addressId', () => this.loadRates());

        try {
            const [{ data: addresses }] = await Promise.all([api.get(endpoints.addresses.index), this.$store.cart.load()]);
            this.addresses = addresses;
            this.addressId = (addresses.find((a) => a.is_default) ?? addresses[0])?.id ?? null;
            this.formOpen = addresses.length === 0;
        } catch (error) {
            this.loadError = error instanceof ApiError ? error.message : 'Halaman checkout tidak dapat dimuat. Coba muat ulang.';
        } finally {
            this.loaded = true;
        }

        if (this.formOpen) this.loadProvinces();
    },

    /**
     * REG-01. Only the address form needs it, so a failure (Jubelio down, cache empty) is
     * shown in the form and never blocks paying with a saved address.
     */
    async loadProvinces() {
        if (this.provinces.length || this.regionLoading === 'provinces') return;

        this.regionLoading = 'provinces';
        try {
            const { data } = await api.get(endpoints.regions.provinces);
            this.provinces = data;
            this.formMessage = null;
        } catch (error) {
            if (!(error instanceof ApiError)) throw error;
            this.formMessage = error.message;
        } finally {
            this.regionLoading = null;
        }
    },

    get cart() {
        return this.$store.cart.cart ?? EMPTY_CART;
    },

    get address() {
        return this.addresses.find((a) => a.id === this.addressId) ?? null;
    },

    get rate() {
        return this.rates.find((r) => r.rate_id === this.rateId) ?? null;
    },

    // Kept apart like orders.shipping_cost / orders.insurance_cost; discount and tax are always 0 in v1.
    get shippingCost() {
        return this.rate?.cost ?? 0;
    },

    get insuranceCost() {
        return this.rate?.insurance_cost ?? 0;
    },

    get grandTotal() {
        return this.cart.summary.subtotal + this.shippingCost + this.insuranceCost;
    },

    /** Why "Bayar" is disabled, mirroring ORD-01's 4xx checks; null when ready. */
    get blockedReason() {
        if (this.cart.items.length === 0) return 'Keranjang kosong';
        if (this.cart.summary.has_issue) return 'Perbaiki keranjang dulu';
        if (!this.address) return 'Pilih alamat pengiriman';
        if (!this.rate) return 'Pilih kurir pengiriman';
        if (this.note.length > 255) return 'Catatan terlalu panjang';
        return null;
    },

    // --- Addresses (ADDR-01 / ADDR-02, REG-01 … REG-04) -----------------------

    formatAddress(a) {
        return `${a.address_line}, ${a.subdistrict.name}, ${a.district.name}, ${a.city.name}, ${a.province.name} ${a.post_code}`;
    },

    /**
     * Changing a level clears the levels below it and loads the choices of the next one
     * (REG-02 … REG-04 depend on the parent). The post code is filled from the chosen
     * kelurahan when Jubelio knows it.
     */
    async pickRegion(level) {
        const order = ['province_id', 'city_id', 'district_id', 'subdistrict_id'];
        const lists = ['cities', 'districts', 'subdistricts'];
        const below = order.indexOf(level) + 1;
        order.slice(below).forEach((key) => (this.form[key] = ''));
        lists.slice(below - 1).forEach((key) => (this[key] = []));

        if (level === 'subdistrict_id') {
            const postCode = this.subdistricts.find((s) => s.id === this.form.subdistrict_id)?.post_code;
            if (postCode) this.form.post_code = postCode;
            return;
        }

        const parentId = this.form[level];
        if (!parentId) return;

        const [endpoint, param, list] = {
            province_id: [endpoints.regions.cities, 'province_id', 'cities'],
            city_id: [endpoints.regions.districts, 'city_id', 'districts'],
            district_id: [endpoints.regions.subdistricts, 'district_id', 'subdistricts'],
        }[level];

        this.regionLoading = list;
        this.formMessage = null;
        try {
            const { data } = await api.get(endpoint, { query: { [param]: parentId } });
            // The customer may have picked something else while this was loading.
            if (this.form[level] === parentId) this[list] = data;
        } catch (error) {
            if (!(error instanceof ApiError)) throw error;
            this.formMessage = error.message;
        } finally {
            this.regionLoading = null;
        }
    },

    openForm() {
        this.form = emptyForm();
        this.form.is_default = this.addresses.length === 0;
        this.formErrors = {};
        this.formMessage = null;
        this.cities = this.districts = this.subdistricts = [];
        this.formOpen = true;
        this.loadProvinces();
    },

    /** Same rules as ADDR-02, so the user sees errors before the request. */
    validateForm() {
        const f = this.form;
        const errors = {};
        if (f.label.length > 50) errors.label = 'Maksimal 50 karakter.';
        if (f.recipient_name.trim().length < 2 || f.recipient_name.length > 100) errors.recipient_name = 'Nama penerima 2–100 karakter.';
        if (!/^(08|628)\d{8,13}$/.test(f.phone.replace(/[\s-]/g, ''))) errors.phone = 'Nomor HP Indonesia 10–15 digit, diawali 08 atau 628.';
        if (f.address_line.trim().length < 10 || f.address_line.length > 255) errors.address_line = 'Alamat lengkap 10–255 karakter (jalan, nomor, RT/RW, patokan).';
        if (!f.province_id) errors.province_id = 'Pilih provinsi.';
        if (!f.city_id) errors.city_id = 'Pilih kota/kabupaten.';
        if (!f.district_id) errors.district_id = 'Pilih kecamatan.';
        if (!f.subdistrict_id) errors.subdistrict_id = 'Pilih kelurahan/desa.';
        if (!/^\d{5}$/.test(f.post_code)) errors.post_code = 'Kode pos 5 digit angka.';
        this.formErrors = errors;
        return Object.keys(errors).length === 0;
    },

    /** ADDR-02, then select the returned Address. */
    async saveAddress() {
        if (this.saving || !this.validateForm()) return;

        this.saving = true;
        this.formMessage = null;
        try {
            const f = this.form;
            const { data: address } = await api.post(endpoints.addresses.index, {
                ...f,
                label: f.label.trim() || null,
                recipient_name: f.recipient_name.trim(),
                phone: f.phone.replace(/[\s-]/g, ''),
                address_line: f.address_line.trim(),
            });

            if (address.is_default) this.addresses.forEach((a) => (a.is_default = false));
            this.addresses.push(address);
            this.formOpen = false;
            this.addressId = address.id;
        } catch (error) {
            if (!(error instanceof ApiError)) throw error;
            // Field messages from the server show under their inputs; the rest in a banner.
            this.formErrors = Object.fromEntries(Object.entries(error.errors).map(([field, messages]) => [field, messages[0]]));
            this.formMessage = error.code === 'VALIDATION_ERROR' ? 'Periksa kembali isian yang ditandai.' : error.message;
        } finally {
            this.saving = false;
        }
    },

    // --- Shipping (SHIP-01) -----------------------------------------------------

    /** SHIP-01: the package is read from the cart by the server; only the address is sent. */
    async loadRates() {
        this.rateId = null;
        this.rates = [];
        this.ratesError = null;
        if (!this.address || this.cart.items.length === 0) return;

        const address = this.address;
        this.ratesLoading = true;
        try {
            const { data } = await api.post(endpoints.shipping.rates, { address_id: address.id });
            if (this.address !== address) return; // A newer request replaced this one.
            this.rates = data.rates;
            this.ratesExpireAt = data.expires_at ? Date.parse(data.expires_at) : Date.now() + RATE_TTL_FALLBACK_MS;
        } catch (error) {
            if (!(error instanceof ApiError)) throw error;
            if (this.address === address) this.ratesError = error.message;
        } finally {
            if (this.address === address) this.ratesLoading = false;
        }
    },

    serviceTypeLabel(type) {
        return SERVICE_TYPES[type] ?? type;
    },

    // --- Pay (ORD-01) -----------------------------------------------------------

    /**
     * ORD-01, then the browser goes to the DANA checkout page (payment.checkout_url). The server empties the cart on
     * success, so the badge is refreshed before leaving.
     */
    async pay() {
        if (this.blockedReason || this.paying) return;
        if (Date.now() > this.ratesExpireAt) {
            this.payError = 'Ongkir sudah kedaluwarsa (lebih dari 30 menit). Pilih kurir lagi.';
            this.loadRates();
            return;
        }

        const body = { address_id: this.addressId, rate_id: this.rateId, note: this.note.trim() || null, expected_grand_total: this.grandTotal };
        const bodyKey = JSON.stringify(body);
        if (this.payKeyBody !== bodyKey) {
            this.payKey = uuid();
            this.payKeyBody = bodyKey;
        }

        this.paying = true;
        this.payError = null;
        try {
            const { data } = await api.post(endpoints.checkout, body, { headers: { 'Idempotency-Key': this.payKey } });
            await this.$store.cart.refresh();
            window.location.href = data.redirect_url;
        } catch (error) {
            if (!(error instanceof ApiError)) throw error;
            this.payError = error.message;
            await this.recoverFrom(error);
            this.paying = false;
        }
        // On success the page is leaving; `paying` stays true so the button cannot be clicked again.
    },

    /** Re-reads whatever the refused ORD-01 says is out of date. */
    async recoverFrom(error) {
        if (error.code === 'CART_HAS_ISSUES' && error.body.cart) {
            this.$store.cart.setFromCart(error.body.cart);
        } else if (['OUT_OF_STOCK', 'PRICE_CHANGED'].includes(error.code)) {
            await this.$store.cart.load().catch(() => {});
        }
        if (['RATE_EXPIRED', 'PRICE_CHANGED'].includes(error.code)) await this.loadRates();
    },
});
