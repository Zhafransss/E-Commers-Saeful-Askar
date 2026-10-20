<?php

namespace App\Services\Webhooks\Handlers;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\WebhookEvent;
use App\Services\Gapura\GapuraStatus;
use App\Services\Shop\OrderPaymentService;
use App\Services\Shop\PaymentOutcome;
use App\Services\Webhooks\WebhookEventIgnored;
use App\Services\Webhooks\WebhookEventRejected;
use App\Services\Webhooks\WebhookHandler;
use Carbon\CarbonImmutable;

/**
 * WH-01 · Gapura's Finish Notify (docs/api/webhooks.md). `00` and `02` mean paid, `05` means DANA closed the
 * order. The rules themselves are in OrderPaymentService; this only reads the notification and says how the
 * event ends: processed, ignored (nothing for the website to do), or failed (a human must look).
 */
class GapuraPaymentNotified implements WebhookHandler
{
    public function __construct(private readonly OrderPaymentService $payments) {}

    public function handle(WebhookEvent $event): void
    {
        $payload = $event->payload;
        $number = (string) ($payload['originalPartnerReferenceNo'] ?? '');

        $order = Order::with(['payment', 'user'])->where('order_number', $number)->first()
            ?? throw new WebhookEventIgnored("Order {$number} tidak dikenal.");

        $merchantId = (string) config('gapura.merchant_id');
        if ($merchantId !== '' && isset($payload['merchantId']) && $payload['merchantId'] !== $merchantId) {
            throw new WebhookEventRejected('merchantId pada notifikasi bukan milik toko ini.');
        }

        $code = (string) ($payload['latestTransactionStatus'] ?? '');

        match (GapuraStatus::toPaymentStatus($code)) {
            PaymentStatus::Paid => $this->paid($order, $payload),
            PaymentStatus::Expired => $this->expired($order),
            default => throw new WebhookEventIgnored("Status {$code} tidak mengubah pesanan."),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function paid(Order $order, array $payload): void
    {
        $info = $payload['additionalInfo']['paymentInfo'] ?? [];
        $option = $info['payOptionInfos'][0] ?? [];
        $paidTime = $info['paidTime'] ?? null;

        $outcome = $this->payments->applyPaid(
            $order,
            (int) round((float) ($payload['amount']['value'] ?? 0)),
            $paidTime ? CarbonImmutable::parse($paidTime) : null,
            $option['payMethod'] ?? null,
            $option['payOption'] ?? null,
        );

        match ($outcome) {
            PaymentOutcome::Applied => null,
            PaymentOutcome::Unchanged => throw new WebhookEventIgnored('Order sudah dibayar.'),
            PaymentOutcome::TooLate => throw new WebhookEventRejected('Dibayar setelah dibatalkan — perlu refund/proses manual.'),
            PaymentOutcome::AmountMismatch => throw new WebhookEventRejected('Jumlah yang dibayar berbeda dari tagihan; order tidak diubah.'),
        };
    }

    private function expired(Order $order): void
    {
        if ($this->payments->applyExpired($order) === PaymentOutcome::Unchanged) {
            throw new WebhookEventIgnored('Order tidak sedang menunggu pembayaran.');
        }
    }
}
