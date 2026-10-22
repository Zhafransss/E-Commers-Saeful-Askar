/**
 * Every /api/v1 path in one place (docs/api/README.md "Indeks endpoint").
 * Paths are relative to /api/v1; the client adds the prefix.
 * Deferred to v1.1 and not listed: ADM-01, ADM-15, ADM-16, ADM-17.
 */

const seg = (value) => encodeURIComponent(value);

export const endpoints = {
    auth: {
        register: '/auth/register', // AUTH-02
        login: '/auth/login', // AUTH-03
        logout: '/auth/logout', // AUTH-04
        me: '/auth/me', // AUTH-05
        profile: '/auth/profile', // AUTH-06 (PUT)
        password: '/auth/password', // AUTH-07 (PUT)
        forgotPassword: '/auth/forgot-password', // AUTH-08
        resetPassword: '/auth/reset-password', // AUTH-09
    },
    categories: {
        index: '/categories', // CAT-01
    },
    products: {
        index: '/products', // CAT-02
        show: (slug) => `/products/${seg(slug)}`, // CAT-03
    },
    cart: {
        show: '/cart', // CART-01 (GET), CART-05 (DELETE)
        items: '/cart/items', // CART-02
        item: (id) => `/cart/items/${seg(id)}`, // CART-03 (PATCH), CART-04 (DELETE)
        count: '/cart/count', // CART-06
    },
    regions: {
        provinces: '/regions/provinces', // REG-01
        cities: '/regions/cities', // REG-02 ?province_id=
        districts: '/regions/districts', // REG-03 ?city_id=
        subdistricts: '/regions/subdistricts', // REG-04 ?district_id=
    },
    addresses: {
        index: '/addresses', // ADDR-01 (GET), ADDR-02 (POST)
        show: (id) => `/addresses/${seg(id)}`, // ADDR-03 (GET), ADDR-04 (PUT), ADDR-05 (DELETE)
        setDefault: (id) => `/addresses/${seg(id)}/default`, // ADDR-06
    },
    shipping: {
        rates: '/shipping/rates', // SHIP-01
    },
    checkout: '/checkout', // ORD-01 (needs Idempotency-Key header)
    orders: {
        index: '/orders', // ORD-02
        show: (orderNumber) => `/orders/${seg(orderNumber)}`, // ORD-03
        payment: (orderNumber) => `/orders/${seg(orderNumber)}/payment`, // ORD-04
        cancel: (orderNumber) => `/orders/${seg(orderNumber)}/cancel`, // ORD-05
    },
    admin: {
        orders: '/admin/orders', // ADM-09
        order: (orderNumber) => `/admin/orders/${seg(orderNumber)}`, // ADM-10
        pushJubelio: (orderNumber) => `/admin/orders/${seg(orderNumber)}/push-jubelio`, // ADM-11
        refreshPayment: (orderNumber) => `/admin/orders/${seg(orderNumber)}/refresh-payment`, // ADM-12
        sync: '/admin/sync', // ADM-13
        syncLogs: '/admin/sync-logs', // ADM-14
        users: '/admin/users', // ADM-18
        user: (id) => `/admin/users/${seg(id)}`, // ADM-19
        products: '/admin/products', // ADM-20
        shipments: '/admin/shipments', // ADM-21
        refreshShipments: '/admin/shipments/refresh', // ADM-22
        refreshPayments: '/admin/orders/refresh-payments', // ADM-23
        sendInvoice: (orderNumber) => `/admin/orders/${seg(orderNumber)}/send-invoice`, // ADM-24
    },
};
