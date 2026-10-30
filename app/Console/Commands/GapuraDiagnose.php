<?php

namespace App\Console\Commands;

use App\Services\Gapura\GapuraClient;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sends one Create Order (or Query Payment) to DANA and prints exactly what went over the wire: the request
 * headers and body, and the full response, which DANA support asks for when a call fails. The private key and
 * the client secret are never part of a request, so the output can be pasted to DANA; X-SIGNATURE is cut short.
 */
class GapuraDiagnose extends Command
{
    protected $signature = 'gapura:diagnose
        {--amount=10000 : Nominal Create Order uji, dalam rupiah}
        {--status= : Nomor pesanan yang dicek lewat Query Payment, ganti Create Order}';

    protected $description = 'Kirim satu request ke DANA dan tampilkan request serta respons lengkapnya';

    public function handle(): int
    {
        $history = [];
        $stack = HandlerStack::create();
        $stack->push(Middleware::history($history));
        $client = new GapuraClient(config('gapura'), new Client(['handler' => $stack, 'timeout' => 15, 'connect_timeout' => 15, 'http_errors' => true]));

        $this->components->info('Environment: '.config('gapura.env').', merchant: '.config('gapura.merchant_id').', MCC: '.config('gapura.mcc'));

        try {
            if (filled($this->option('status'))) {
                $client->queryPayment((string) $this->option('status'));
            } else {
                $number = 'DIAG-'.now()->format('YmdHis');
                $client->createOrder($number, (int) $this->option('amount'), now()->addMinutes(10), "Uji {$number}", route('checkout.finish', ['order' => $number]));
            }
            $this->components->info('DANA menjawab sukses.');
        } catch (Throwable $e) {
            $this->components->warn('Gagal: '.$e->getMessage());
        }

        foreach ($history as $index => $call) {
            $request = $call['request'];
            $error = $call['error'] ?? null;
            $response = $call['response'] ?? (is_object($error) && method_exists($error, 'getResponse') ? $error->getResponse() : null);
            $this->newLine();
            $this->line('=== Percobaan '.($index + 1).' ===');
            $this->line($request->getMethod().' '.$request->getUri());
            foreach ($request->getHeaders() as $name => $values) {
                $value = implode(', ', $values);
                $this->line("{$name}: ".(strtoupper($name) === 'X-SIGNATURE' ? substr($value, 0, 16).'...(dipotong)' : $value));
            }
            $this->line('Body: '.(string) $request->getBody());
            $this->line('--- Respons ---');
            $this->line($response === null ? '(tidak ada respons)' : 'HTTP '.$response->getStatusCode());
            if ($response !== null) {
                foreach ($response->getHeaders() as $name => $values) {
                    $this->line("{$name}: ".implode(', ', $values));
                }
                $this->line('Body: '.(string) $response->getBody());
            }
        }

        return self::SUCCESS;
    }
}
