<x-mail::message>
# Pesanan Anda sudah dikirim

Halo {{ $order->shipping_recipient_name }}, pesanan **{{ $order->order_number }}** sudah diserahkan ke kurir.

@if ($order->tracking_no)
**Nomor resi:** {{ $order->tracking_no }}@if ($order->jubelio_courier) ({{ $order->jubelio_courier }})@endif

@endif
<x-mail::button :url="$url">
Lacak pesanan
</x-mail::button>

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
