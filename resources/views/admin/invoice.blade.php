@php
    $rp = fn ($amount) => 'Rp'.number_format((int) $amount, 0, ',', '.');
    $date = fn ($value) => $value?->timezone('Asia/Jakarta')->translatedFormat('d F Y');
    $statusLabel = $paid ? 'LUNAS' : strtoupper($order->status->label());
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $number }}</title>
    <style>
        @page { margin: 36px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 22px; margin: 0; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #666; }
        .store { font-size: 14px; font-weight: bold; }
        .badge { display: inline-block; padding: 4px 10px; border: 1.5px solid #111; font-weight: bold; font-size: 11px; }
        .badge.paid { border-color: #15803d; color: #15803d; }
        .head td { vertical-align: top; }
        .box { margin-top: 18px; }
        .label { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #666; margin-bottom: 3px; }
        .items { margin-top: 18px; }
        .items th { background: #111; color: #fff; text-align: left; padding: 6px 8px; font-size: 9px; }
        .items td { padding: 7px 8px; border-bottom: 1px solid #ddd; vertical-align: top; }
        .right { text-align: right; }
        .totals { margin-top: 10px; width: 45%; margin-left: 55%; }
        .totals td { padding: 4px 8px; }
        .totals .grand td { border-top: 2px solid #111; font-weight: bold; font-size: 12px; padding-top: 8px; }
        .foot { margin-top: 30px; font-size: 9px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 60%">
                <div class="store">{{ $store['name'] }}</div>
                <div class="muted" style="margin-top: 4px">{{ $store['address'] }}</div>
                <div class="muted">{{ $store['phone'] }}</div>
            </td>
            <td class="right">
                <h1>INVOICE</h1>
                <div style="margin-top: 4px"><strong>{{ $number }}</strong></div>
                <div class="muted">Tanggal: {{ $date($order->created_at) }}</div>
                <div style="margin-top: 8px"><span class="badge {{ $paid ? 'paid' : '' }}">{{ $statusLabel }}</span></div>
            </td>
        </tr>
    </table>

    <table class="box">
        <tr>
            <td style="width: 50%; vertical-align: top">
                <div class="label">Ditagihkan kepada</div>
                <strong>{{ $order->user->name }}</strong><br>
                {{ $order->user->email }}<br>
                {{ $order->user->phone }}
            </td>
            <td style="vertical-align: top">
                <div class="label">Dikirim ke</div>
                <strong>{{ $order->shipping_recipient_name }}</strong> ({{ $order->shipping_phone }})<br>
                {{ $order->shipping_address_line }}<br>
                {{ collect([$order->shipping_subdistrict, $order->shipping_district, $order->shipping_city, $order->shipping_province])->filter()->implode(', ') }} {{ $order->shipping_post_code }}
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%">No</th>
                <th>Produk</th>
                <th class="right" style="width: 8%">Qty</th>
                <th class="right" style="width: 18%">Harga</th>
                <th class="right" style="width: 18%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>
                        <strong>{{ $item->product_name }}</strong>
                        @if ($item->variant_name)<br><span class="muted">{{ $item->variant_name }}</span>@endif
                        <br><span class="muted">SKU: {{ $item->sku }}</span>
                    </td>
                    <td class="right">{{ $item->qty }}</td>
                    <td class="right">{{ $rp($item->price) }}</td>
                    <td class="right">{{ $rp($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ $rp($order->subtotal) }}</td></tr>
        <tr><td>Ongkos kirim ({{ $order->courier_name }} {{ $order->service_name }})</td><td class="right">{{ $rp($order->shipping_cost) }}</td></tr>
        @if ($order->insurance_cost > 0)<tr><td>Asuransi pengiriman</td><td class="right">{{ $rp($order->insurance_cost) }}</td></tr>@endif
        @if ($order->discount > 0)<tr><td>Diskon</td><td class="right">-{{ $rp($order->discount) }}</td></tr>@endif
        @if ($order->tax > 0)<tr><td>Pajak</td><td class="right">{{ $rp($order->tax) }}</td></tr>@endif
        <tr class="grand"><td>Total</td><td class="right">{{ $rp($order->grand_total) }}</td></tr>
    </table>

    <div class="foot">
        @if ($paid && $order->payment?->paid_at)
            Dibayar pada {{ $date($order->payment->paid_at) }}@if ($order->payment->payment_channel) melalui {{ $order->payment->payment_channel }}@endif.
        @elseif (! $paid)
            Pesanan ini berstatus {{ strtolower($order->status->label()) }}; invoice ini belum menandakan pembayaran.
        @endif
        @if ($order->note)<br>Catatan pelanggan: {{ $order->note }}@endif
        <br>Terima kasih telah berbelanja di {{ $store['name'] }}.
    </div>
</body>
</html>
