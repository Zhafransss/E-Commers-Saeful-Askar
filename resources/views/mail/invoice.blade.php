<x-mail::message>
# Invoice pesanan {{ $order->order_number }}

Halo {{ $order->shipping_recipient_name }}, terlampir invoice **{{ $number }}** untuk pesanan Anda dalam bentuk PDF.

<x-mail::panel>
Total pesanan: **Rp{{ number_format($order->grand_total, 0, ',', '.') }}**
</x-mail::panel>

Simpan invoice ini sebagai bukti pembelian. Status dan riwayat pesanan bisa dilihat kapan saja di akun Anda.

<x-mail::button :url="$url">
Lihat pesanan
</x-mail::button>

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
