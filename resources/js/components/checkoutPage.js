import * as staticCart from '../static/cart.js';

/**
 * Alpine.data('checkoutPage'): the /checkout page (docs/api/checkout-orders.md ORD-01,
 * shipping.md SHIP-01, addresses-regions.md ADDR-01/02 and REG-01 … REG-04).
 *
 * Static phase: the cart comes from the localStorage cart, addresses and regions from
 * resources/static/checkout.php, and shipping rates from sampleRates() below. Each
 * place that will call the API is marked with the endpoint to use.
 */
const ORIGIN_CITY_ID = '3216'; // KAB. BEKASI, gudang Tambun Utara
const JABODETABEK_PROVINCES = ['31', '36']; // DKI Jakarta, Banten (Tangerang)
const JABODETABEK_CITIES = ['3216', '3275', '3201', '3271', '3276'];
const RATE_TTL_MS = 30 * 60 * 1000;
const LAST_ORDER_KEY = 'saefulaskar.last-order';

/**
 * SHIP-01 sample. The real rates come from Jubelio Shipment through the backend;
 * these numbers only make the page behave plausibly (heavier and farther costs more).
 */
function sampleRates(address, totalWeight) {
    const kg = Math.max(1, Math.ceil(totalWeight / 1000));
    const zone = address.city.id === ORIGIN_CITY_ID ? 0
        : JABODETABEK_CITIES.includes(address.city.id) || JABODETABEK_PROVINCES.includes(address.province.id) ? 1 : 2;
    const etd = (ranges) => ranges[zone];
    const services = [];

    if (zone === 0 && kg <= 20) {
        services.push(['gosend', 'GoSend', 'INSTANT', 'GoSend Instant', 'instant', 25000 + kg * 2500, [0, 0]]);
    }
    if (kg <= 30) {
        services.push(['jne', 'JNE', 'REG', 'JNE Reguler', 'regular', kg * [9000, 12000, 24000][zone], etd([[1, 2], [2, 3], [3, 5]])]);
        services.push(['jnt', 'J&T Express', 'EZ', 'J&T EZ', 'regular', kg * [8500, 11500, 23000][zone], etd([[1, 2], [2, 3], [3, 6]])]);
    }
    const cargoKg = Math.max(10, kg);
    services.push(['jne', 'JNE', 'JTR', 'JNE Trucking', 'cargo', cargoKg * [5000, 6500, 11000][zone], etd([[2, 3], [3, 5], [5, 8]])]);
    services.push(['jnt', 'J&T Express', 'CARGO', 'J&T Cargo', 'cargo', cargoKg * [4800, 6200, 10500][zone], etd([[2, 4], [3, 5], [5, 9]])]);
    services.push(['sap', 'SAP Express', 'CARGO', 'SAP Cargo', 'cargo', cargoKg * [5200, 6800, 11500][zone], etd([[1, 3], [2, 4], [4, 7]])]);

    return services
        .map(([courier_code, courier_name, service_code, service_name, service_type, cost, [min, max]]) => ({
            rate_id: `rt_${Math.random().toString(16).slice(2, 10)}`,
            courier_code,
            courier_name,
            service_code,
            service_name,
            service_type,
            cost: Math.round(cost / 500) * 500,
            insurance_cost: 0,
            etd_min_days: min,
            etd_max_days: max,
            etd_text: max === 0 ? 'Hari ini' : min === max ? `${min} hari` : `${min}–${max} hari`,
        }))
        .sort((a, b) => a.cost - b.cost);
}

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

export default ({ addresses, regions }) => ({
    cart: staticCart.load(),
    addresses,
    regions,

    addressId: (addresses.find((a) => a.is_default) ?? addresses[0])?.id ?? null,
    formOpen: addresses.length === 0,
    form: emptyForm(),
    formErrors: {},

    rates: [],
    ratesLoading: false,
    ratesError: null,
    ratesExpireAt: null,
    rateId: null,

    note: '',
    paying: false,
    payError: null,

    init() {
        this.$store.cart.setFromCart(this.cart);
        this.$watch('addressId', () => this.loadRates());
        this.loadRates();
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

    childrenOf(list, id) {
        return list.find((r) => r.id === id)?.children ?? [];
    },

    get cities() {
        return this.childrenOf(this.regions, this.form.province_id);
    },

    get districts() {
        return this.childrenOf(this.cities, this.form.city_id);
    },

    get subdistricts() {
        return this.childrenOf(this.districts, this.form.district_id);
    },

    /** Changing a level clears the levels below it (REG-02 … REG-04 depend on the parent). */
    pickRegion(level) {
        const order = ['province_id', 'city_id', 'district_id', 'subdistrict_id'];
        order.slice(order.indexOf(level) + 1).forEach((key) => (this.form[key] = ''));
        if (level === 'subdistrict_id') {
            const postCode = this.subdistricts.find((s) => s.id === this.form.subdistrict_id)?.post_code;
            if (postCode) this.form.post_code = postCode;
        }
    },

    openForm() {
        this.form = emptyForm();
        this.form.is_default = this.addresses.length === 0;
        this.formErrors = {};
        this.formOpen = true;
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

    /** ADDR-02: POST endpoints.addresses.index, then select the returned Address. */
    saveAddress() {
        if (!this.validateForm()) return;

        const f = this.form;
        const pick = (list, id) => ({ id, name: list.find((r) => r.id === id).name });
        const address = {
            id: Math.max(0, ...this.addresses.map((a) => a.id)) + 1,
            label: f.label.trim() || null,
            recipient_name: f.recipient_name.trim(),
            phone: f.phone.replace(/[\s-]/g, '').replace(/^628/, '08'),
            address_line: f.address_line.trim(),
            province: pick(this.regions, f.province_id),
            city: pick(this.cities, f.city_id),
            district: pick(this.districts, f.district_id),
            subdistrict: pick(this.subdistricts, f.subdistrict_id),
            post_code: f.post_code,
            is_default: f.is_default || this.addresses.length === 0,
        };

        if (address.is_default) this.addresses.forEach((a) => (a.is_default = false));
        this.addresses.push(address);
        this.formOpen = false;
        this.addressId = address.id;
    },

    // --- Shipping (SHIP-01) -----------------------------------------------------

    /** SHIP-01: POST endpoints.shipping.rates { address_id }. */
    loadRates() {
        this.rateId = null;
        this.rates = [];
        this.ratesError = null;
        if (!this.address || this.cart.items.length === 0) return;

        this.ratesLoading = true;
        const address = this.address;
        setTimeout(() => {
            if (this.address !== address) return; // A newer request replaced this one.
            this.rates = sampleRates(address, this.cart.summary.total_weight);
            this.ratesExpireAt = Date.now() + RATE_TTL_MS;
            this.ratesLoading = false;
            if (this.rates.length === 0) this.ratesError = 'Belum ada kurir yang bisa mengirim ke alamat ini. Silakan hubungi toko.';
        }, 700);
    },

    serviceTypeLabel(type) {
        return { instant: 'Instan', same_day: 'Same day', regular: 'Reguler', cargo: 'Kargo' }[type] ?? type;
    },

    // --- Pay (ORD-01) -----------------------------------------------------------

    /**
     * ORD-01: api.post(endpoints.checkout, body, { headers: { 'Idempotency-Key': key } }),
     * then window.location = data.redirect_url (the Xendit invoice). The key is created
     * once per click and reused if the same request is retried.
     */
    pay() {
        if (this.blockedReason || this.paying) return;
        if (Date.now() > this.ratesExpireAt) {
            this.payError = 'Ongkir sudah kedaluwarsa (lebih dari 30 menit). Pilih kurir lagi.';
            this.loadRates();
            return;
        }

        const key = crypto.randomUUID();
        const body = { address_id: this.addressId, rate_id: this.rateId, note: this.note.trim() || null, expected_grand_total: this.grandTotal };
        this.paying = true;
        this.payError = null;
        console.debug('[checkout] ORD-01', { 'Idempotency-Key': key, ...body });

        setTimeout(() => {
            const orderNumber = `WEB-${String(124 + Math.floor(Math.random() * 800)).padStart(6, '0')}`;
            const order = {
                order_number: orderNumber,
                status: 'pending_payment',
                // Snapshot per order_items row (docs/erd.md); weight_gram is per unit.
                items: this.cart.items.map((item) => ({
                    variant_id: item.variant.id,
                    sku: item.variant.sku,
                    product_name: item.product.name,
                    variant_name: item.variant.variation_values.map((v) => v.value).join(', ') || null,
                    image_url: item.variant.image_url,
                    unit: staticCart.unitOf(item.variant.id),
                    price: item.variant.price,
                    qty: item.qty,
                    weight_gram: item.variant.package_weight ?? 0,
                    subtotal: item.subtotal,
                })),
                shipping_address: { ...this.address },
                shipping: { ...this.rate },
                amounts: {
                    subtotal: this.cart.summary.subtotal,
                    shipping_cost: this.shippingCost,
                    insurance_cost: this.insuranceCost,
                    discount: 0,
                    tax: 0,
                    grand_total: this.grandTotal,
                },
                note: body.note,
                payment_expires_at: new Date(Date.now() + 2 * 60 * 60 * 1000).toISOString(),
            };
            try {
                window.sessionStorage.setItem(LAST_ORDER_KEY, JSON.stringify(order));
            } catch {
                // The finish page then shows the order number only.
            }

            // ORD-01 empties the cart on success (acceptance criterion 9).
            this.cart.items = [];
            staticCart.save(staticCart.recompute(this.cart));
            this.$store.cart.setFromCart(this.cart);

            // Live: the Xendit invoice page, which returns to /checkout/finish?order=…
            window.location.href = `${this.$root.dataset.finishUrl}?order=${orderNumber}`;
        }, 1200);
    },
});
