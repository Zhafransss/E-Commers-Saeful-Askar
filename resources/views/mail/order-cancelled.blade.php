<x-mail::message>
# Pesanan dibatalkan

Halo {{ $order->shipping_recipient_name }}, pesanan **{{ $order->order_number }}** dibatalkan.

@if ($order->cancel_reason)
Alasan: {{ $order->cancel_reason }}.
@endif

Tidak ada tagihan yang perlu Anda bayar untuk pesanan ini. Kalau sudah terlanjur membayar, hubungi kami dengan menyebut nomor pesanan di atas.

<x-mail::button :url="$url">
Lihat pesanan
</x-mail::button>

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
