import { api, ApiError } from '../api/client.js';
import { endpoints } from '../api/endpoints.js';
import { formatDateTime, formatRupiah } from '../utils/format.js';

/**
 * The WhatsApp link of "Hubungi admin": a chat with the store with the unpaid order already written out, so the
 * customer only has to press send (and attach the proof of payment).
 */
export function paymentReportUrl(phone, order) {
    const items = order.items.map((item) => `- ${item.product_name}${item.variant_name ? ` (${item.variant_name})` : ''} × ${item.qty}`);
    const text = [
        'Halo Admin Saeful Askar, saya sudah membayar pesanan berikut, tetapi statusnya di website masih "Menunggu pembayaran". Mohon dicek.',
        '',
        `Nomor pesanan: ${order.order_number}`,
        `Tanggal pesanan: ${formatDateTime(order.created_at)}`,
        `Total: ${formatRupiah(order.amounts.grand_total)}`,
        `Nama penerima: ${order.shipping_address.recipient_name}`,
        'Produk:',
        ...items,
        '',
        'Bukti pembayaran saya lampirkan di chat ini. Terima kasih.',
    ].join('\n');

    return `https://api.whatsapp.com/send?phone=${encodeURIComponent(phone)}&text=${encodeURIComponent(text)}`;
}

/**
 * Shared by the finish page and the order page while an order waits for payment (docs/api/checkout-orders.md
 * ORD-06): "Cek status pembayaran" asks DANA right now, and "Hubungi admin" opens WhatsApp. The component
 * spreading this has `orderNumber` and `order`, and `onPaymentChecked()` to react to the new order.
 */
export function paymentHelp(whatsapp) {
    return {
        whatsapp,
        checkingPayment: false,
        paymentNotice: null,
        paymentNoticeError: false,

        async checkPayment() {
            if (this.checkingPayment) return;
            this.checkingPayment = true;
            this.paymentNotice = null;
            this.paymentNoticeError = false;
            try {
                const { data, message } = await api.post(endpoints.orders.refreshPayment(this.orderNumber));
                this.order = data;
                this.paymentNotice = message;
                this.paymentNoticeError = data.status === 'pending_payment';
                this.onPaymentChecked?.();
            } catch (error) {
                if (!(error instanceof ApiError)) throw error;
                this.paymentNoticeError = true;
                this.paymentNotice = error.status === 429 ? 'Terlalu sering memeriksa. Tunggu sebentar lalu coba lagi.' : error.message;
            } finally {
                this.checkingPayment = false;
            }
        },

        reportUrl() {
            return this.order ? paymentReportUrl(this.whatsapp, this.order) : '#';
        },
    };
}
