<?php

namespace App\Console\Commands;

use App\Exceptions\IntegrationException;
use App\Services\Jubelio\JubelioClient;
use Illuminate\Console\Command;

/**
 * INT-JB-14 · Finds the IDs for .env (store, warehouse, tax) instead of guessing
 * them, checks the IDs already in .env, and optionally registers the webhook URLs.
 */
class JubelioSetup extends Command
{
    protected $signature = 'jubelio:setup
        {--register-webhooks : Daftarkan URL WH-02 – WH-05 ke Jubelio}
        {--url= : URL publik website untuk webhook (default APP_URL)}
        {--force : Daftarkan webhook tanpa konfirmasi}';

    protected $description = 'Tampilkan ID toko, gudang, dan pajak Jubelio untuk .env, lalu (opsional) daftarkan webhook';

    /** Jubelio action => route name (routes/webhooks.php). */
    private const WEBHOOKS = [
        'update-product' => 'webhooks.jubelio.product',
        'update-price' => 'webhooks.jubelio.price',
        'update-qty' => 'webhooks.jubelio.stock',
        'update-salesorder' => 'webhooks.jubelio.salesorder',
    ];

    public function handle(JubelioClient $jubelio): int
    {
        try {
            $stores = $jubelio->stores();
            $locations = $jubelio->locations();
            $taxes = $jubelio->taxes();
        } catch (IntegrationException $e) {
            $this->components->error('Gagal mengambil data dari Jubelio: '.$e->detail());

            return self::FAILURE;
        }

        $this->components->info('Toko (JUBELIO_STORE_ID = toko internal "Website Saeful Askar", channel_id 1)');
        $this->table(['store_id', 'store_name', 'channel_id', ''], array_map(fn (array $store) => [
            $store['store_id'] ?? '',
            $store['store_name'] ?? '',
            $store['channel_id'] ?? '',
            (int) ($store['channel_id'] ?? 0) === 1 ? '← toko internal' : '',
        ], $stores));

        $this->components->info('Gudang (JUBELIO_LOCATION_ID = gudang Tambun Utara)');
        $this->table(['location_id', 'location_name'], array_map(fn (array $location) => [
            $location['location_id'] ?? '',
            $location['location_name'] ?? '',
        ], $locations));

        $this->components->info('Pajak (JUBELIO_TAX_ID = "No Tax")');
        $this->table(['tax_id', 'tax_name'], array_map(fn (array $tax) => [
            $tax['tax_id'] ?? '',
            $tax['tax_name'] ?? '',
        ], $taxes));

        $configured = $this->checkConfiguredIds($stores, $locations, $taxes);

        if ($this->option('register-webhooks') && ! $this->registerWebhooks($jubelio)) {
            return self::FAILURE;
        }

        return $configured ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Shows the names behind the IDs in .env, so a wrong ID is noticed here
     * rather than as a sales order in the wrong store.
     *
     * @param  list<array<string, mixed>>  $stores
     * @param  list<array<string, mixed>>  $locations
     * @param  list<array<string, mixed>>  $taxes
     */
    private function checkConfiguredIds(array $stores, array $locations, array $taxes): bool
    {
        $this->components->info('Konfigurasi .env saat ini');

        $checks = [
            ['JUBELIO_STORE_ID', config('jubelio.store_id'), $stores, 'store_id', 'store_name'],
            ['JUBELIO_LOCATION_ID', config('jubelio.location_id'), $locations, 'location_id', 'location_name'],
            ['JUBELIO_TAX_ID', config('jubelio.tax_id'), $taxes, 'tax_id', 'tax_name'],
        ];

        $allFound = true;

        foreach ($checks as [$env, $value, $rows, $idKey, $nameKey]) {
            if (blank($value)) {
                $this->components->twoColumnDetail($env, '<fg=yellow>belum diisi</>');
                $allFound = false;

                continue;
            }

            $match = collect($rows)->first(fn (array $row) => (string) ($row[$idKey] ?? '') === (string) $value);

            if ($match === null) {
                $this->components->twoColumnDetail("{$env} = {$value}", '<fg=red>TIDAK DITEMUKAN di Jubelio</>');
                $allFound = false;

                continue;
            }

            $this->components->twoColumnDetail("{$env} = {$value}", '<fg=green>'.($match[$nameKey] ?? '').'</>');
        }

        return $allFound;
    }

    private function registerWebhooks(JubelioClient $jubelio): bool
    {
        $baseUrl = rtrim((string) ($this->option('url') ?: config('app.url')), '/');

        $urls = array_map(
            fn (string $route) => $baseUrl.route($route, absolute: false),
            self::WEBHOOKS,
        );

        $this->components->info('Webhook yang akan didaftarkan');
        foreach ($urls as $action => $url) {
            $this->components->twoColumnDetail($action, $url);
        }

        if (! str_starts_with($baseUrl, 'https://') || str_contains($baseUrl, 'localhost') || str_contains($baseUrl, '127.0.0.1')) {
            $this->components->warn('URL bukan HTTPS publik; Jubelio tidak akan bisa menjangkaunya. Pakai --url=https://<domain>.');
        }

        if (! $this->option('force') && ! $this->confirm('Daftarkan webhook ini ke Jubelio?')) {
            $this->components->warn('Webhook tidak didaftarkan.');

            return true;
        }

        try {
            $jubelio->registerWebhooks($urls);
        } catch (IntegrationException $e) {
            $this->components->error('Gagal mendaftarkan webhook: '.$e->detail());

            return false;
        }

        $this->components->info('Webhook terdaftar. Isi secret key di Jubelio (Pengaturan → Developer → Webhook) sama dengan JUBELIO_WEBHOOK_SECRET.');

        if (blank(config('jubelio.webhook_signature_header'))) {
            $this->components->warn('JUBELIO_WEBHOOK_SIGNATURE_HEADER belum diisi: webhook pertama akan ditolak, dan nama header yang dikirim Jubelio tercatat di kolom webhook_events.error.');
        }

        return true;
    }
}
