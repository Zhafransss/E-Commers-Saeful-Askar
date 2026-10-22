<x-mail::message>
# Pembayaran diterima

Halo {{ $order->shipping_recipient_name }}, terima kasih! Pembayaran pesanan **{{ $order->order_number }}** sudah kami terima dan pesanan Anda sedang disiapkan oleh gudang.

<x-mail::table>
| Produk | Jumlah | Subtotal |
| :----- | :----: | -------: |
@foreach ($order->items as $item)
| {{ $item->product_name }}@if ($item->variant_name) ({{ $item->variant_name }})@endif | {{ $item->qty }} | Rp{{ number_format($item->subtotal, 0, ',', '.') }} |
@endforeach
| Ongkos kirim ({{ $order->service_name }}) | | Rp{{ number_format($order->shipping_cost + $order->insurance_cost, 0, ',', '.') }} |
| **Total dibayar** | | **Rp{{ number_format($order->grand_total, 0, ',', '.') }}** |
</x-mail::table>

Invoice pesanan ini terlampir dalam bentuk PDF. Nomor resi akan muncul di halaman pesanan setelah barang dikirim.

<x-mail::button :url="$url">
Lihat pesanan
</x-mail::button>

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
