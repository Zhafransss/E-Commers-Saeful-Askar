/**
 * Display helpers for contract data types (docs/api/README.md "Aturan data"):
 * money is integer rupiah, weight is integer grams, timestamps are ISO 8601 +07:00.
 */

const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
const dateTime = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' });
const dateOnly = new Intl.DateTimeFormat('id-ID', { dateStyle: 'long', timeZone: 'Asia/Jakarta' });

/** 6750000 → "Rp 6.750.000" */
export const formatRupiah = (amount) => rupiah.format(amount ?? 0);

/** 32000 → "32 kg", 950 → "950 g" */
export const formatWeight = (grams) =>
    grams >= 1000 ? `${(grams / 1000).toLocaleString('id-ID', { maximumFractionDigits: 1 })} kg` : `${grams ?? 0} g`;

/** "2026-10-14T21:30:00+07:00" → "14 Okt 2026, 21.30" */
export const formatDateTime = (iso) => (iso ? dateTime.format(new Date(iso)) : '');

/** "2026-10-14T21:30:00+07:00" → "14 Oktober 2026" */
export const formatDate = (iso) => (iso ? dateOnly.format(new Date(iso)) : '');
