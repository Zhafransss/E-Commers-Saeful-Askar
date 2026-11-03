<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\WebhookEvent;
use App\Services\Gapura\GapuraClient;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Console\Command;
use Throwable;

/**
 * Collects what DANA's production verification sheet asks for, from one order that was really paid: the Create
 * Order body, DANA's live answer to Query Payment (request and response as sent), and the Finish Notify DANA
 * posted to us. Nothing here changes an order or sends money; it only reads, and asks DANA for the status.
 */
class GapuraBukti extends Command
{
    protected $signature = 'gapura:bukti {order : Nomor pesanan yang sudah dibayar, mis. WS-000023}';

    protected $description = 'Kumpulkan data pesanan asli yang sudah dibayar untuk lembar verifikasi produksi DANA';

    public function handle(): int
    {
        $order = Order::where('order_number', $this->argument('order'))->with('payment')->first();
        $payment = $order?->payment;

        if ($payment === null) {
            $this->components->error('Pesanan atau data pembayarannya tidak ditemukan.');

            return self::FAILURE;
        }

        $this->components->info("Pesanan {$order->order_number}: status {$order->status->value}, pembayaran {$payment->status->value}, Rp {$payment->amount}");

        $this->section('1. Body Request Create Order (disusun ulang dari data tersimpan, URL notify dan return sama seperti saat dibuat)');
        $this->line(json_encode([
            'partnerReferenceNo' => $order->order_number,
            'merchantId' => config('gapura.merchant_id'),
            'amount' => ['value' => number_format($payment->amount, 2, '.', ''), 'currency' => 'IDR'],
            'validUpTo' => $payment->expires_at->format('Y-m-d\TH:i:sP'),
            'urlParams' => [
                ['url' => url(GapuraClient::NOTIFY_PATH), 'type' => 'NOTIFICATION', 'isDeeplink' => 'N'],
                ['url' => route('checkout.finish', ['order' => $order->order_number]), 'type' => 'PAY_RETURN', 'isDeeplink' => 'N'],
            ],
            'additionalInfo' => [
                'mcc' => config('gapura.mcc'),
                'envInfo' => ['sourcePlatform' => 'IPG', 'terminalType' => 'SYSTEM'],
                'order' => ['orderTitle' => "Pesanan {$order->order_number}", 'scenario' => 'REDIRECT'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->section('2. Body Response Create Order (nilai asli dari pesanan; tautan checkout dipotong)');
        $this->line(json_encode([
            'responseCode' => '2005400',
            'responseMessage' => 'Successful',
            'referenceNo' => $payment->gapura_reference_no,
            'partnerReferenceNo' => $order->order_number,
            'webRedirectUrl' => substr((string) $payment->checkout_url, 0, 40).'...(dipotong)',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->section('3. Query Payment ke DANA sekarang (request dan respons asli)');
        $history = [];
        $stack = HandlerStack::create();
        $stack->push(Middleware::history($history));
        $client = new GapuraClient(config('gapura'), new Client(['handler' => $stack, 'timeout' => 15, 'connect_timeout' => 15]));

        try {
            $client->queryPayment($order->order_number);
        } catch (Throwable $e) {
            $this->components->warn('Query gagal: '.$e->getMessage());
        }

        foreach ($history as $call) {
            $this->line('Body Request: '.(string) $call['request']->getBody());
            $response = $call['response'] ?? null;
            $this->line('Body Response: '.($response === null ? '(tidak ada respons)' : (string) $response->getBody()));
        }

        $this->section('4. Finish Notify yang diterima dari DANA (payload asli, tanda tangan valid)');
        $event = WebhookEvent::where('provider', 'gapura')->where('signature_valid', true)
            ->where('idempotency_key', 'like', "gapura:{$order->order_number}:%")->latest('id')->first();

        if ($event === null) {
            $this->components->warn('Belum ada notifikasi dengan tanda tangan valid untuk pesanan ini.');
        } else {
            $this->line('Diterima: '.$event->received_at.', status pemrosesan: '.$event->status->value);
            $this->line('Body Request: '.json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->line('Body Response: {"responseCode":"2005600","responseMessage":"Successful"}');
        }

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("=== {$title} ===");
    }
}
