<?php

namespace App\Services\Jubelio;

use App\Exceptions\IntegrationException;
use App\Exceptions\JubelioApiException;
use App\Services\IntegrationClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

/**
 * The only class allowed to call api2.jubelio.com (docs/api/integrations.md INT-HDL).
 * Endpoint methods (INT-JB-01 – INT-JB-14) are added here as each phase needs them.
 */
class JubelioClient extends IntegrationClient
{
    private const RATE_LIMIT_KEY = 'jubelio:outbound';

    /**
     * @param  array<string, mixed>  $config  config('jubelio')
     */
    public function __construct(
        private readonly array $config,
        private readonly JubelioTokenManager $tokens,
    ) {}

    protected function service(): string
    {
        return 'jubelio';
    }

    /**
     * INT-JB-01 · Login. Callers normally never need this: get()/post() fetch
     * the cached token themselves.
     */
    public function login(): string
    {
        if (blank($this->config['email']) || blank($this->config['password'])) {
            throw new JubelioApiException('jubelio', 'POST', '/login', retryable: false,
                providerMessage: 'JUBELIO_EMAIL dan JUBELIO_PASSWORD belum diisi di .env.');
        }

        try {
            $response = $this->send($this->baseRequest(), 'POST', '/login', ['json' => [
                'email' => $this->config['email'],
                'password' => $this->config['password'],
            ]]);
        } catch (JubelioApiException $e) {
            if ($e->isInvalidCredentials()) {
                Log::channel('integrations')->critical('Jubelio menolak JUBELIO_EMAIL/JUBELIO_PASSWORD.');
            }

            throw $e;
        }

        $token = $response->json('token');

        if (! is_string($token) || $token === '') {
            throw new JubelioApiException('jubelio', 'POST', '/login', $response->status(),
                providerMessage: 'Respons login Jubelio tidak berisi token.');
        }

        return $token;
    }

    /**
     * INT-JB-14 · All stores, internal (channel_id 1) and marketplace. The website
     * uses its own internal store. /marketplace/store/ is not used: it lists
     * marketplace stores only and returns their secrets in extra_info.
     *
     * @return list<array<string, mixed>>
     */
    public function stores(): array
    {
        return $this->get('/locations/store/', ['page' => 1, 'pageSize' => 200])->json('data') ?? [];
    }

    /**
     * INT-JB-14 · Warehouses.
     *
     * @return list<array<string, mixed>>
     */
    public function locations(): array
    {
        return $this->get('/locations/', ['page' => 1, 'pageSize' => 200])->json('data') ?? [];
    }

    /**
     * INT-JB-14 · Taxes ("No Tax" is the one the store uses).
     *
     * @return list<array<string, mixed>>
     */
    public function taxes(): array
    {
        return $this->get('/taxes/', ['page' => 1, 'pageSize' => 200])->json('data') ?? [];
    }

    /**
     * INT-JB-14 · Register webhook URLs.
     *
     * @param  array<string, string>  $urls  action => URL, e.g. 'update-qty' => 'https://…/webhooks/jubelio/stock'
     */
    public function registerWebhooks(array $urls): void
    {
        $this->post('/systemsetting/webhook', [
            'webhooks' => collect($urls)->map(fn (string $url, string $action) => compact('action', 'url'))->values()->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): Response
    {
        return $this->authorized('GET', $path, ['query' => $query]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function post(string $path, array $body = []): Response
    {
        return $this->authorized('POST', $path, ['json' => $body]);
    }

    /**
     * Sends with the cached token. On 401 the token is dropped, a new one is
     * fetched once, and the request is repeated once (INT-HDL).
     *
     * @param  array<string, mixed>  $options
     */
    protected function authorized(string $method, string $path, array $options): Response
    {
        $token = $this->tokens->token(fn () => $this->login());

        try {
            return $this->send($this->baseRequest()->withHeaders(['authorization' => $token]), $method, $path, $options);
        } catch (JubelioApiException $e) {
            if ($e->httpStatus !== 401) {
                throw $e;
            }

            $this->tokens->forget($token);
            $token = $this->tokens->token(fn () => $this->login());

            return $this->send($this->baseRequest()->withHeaders(['authorization' => $token]), $method, $path, $options);
        }
    }

    /**
     * Keeps every caller together below Jubelio's 600 requests/minute by
     * waiting for the next window instead of getting a 429.
     */
    protected function beforeSend(): void
    {
        $limit = (int) $this->config['rate_limit_per_minute'];

        while (RateLimiter::tooManyAttempts(self::RATE_LIMIT_KEY, $limit)) {
            Sleep::for(max(1, RateLimiter::availableIn(self::RATE_LIMIT_KEY)))->seconds();
        }

        RateLimiter::hit(self::RATE_LIMIT_KEY, 60);
    }

    protected function toException(Response $response, string $method, string $path): IntegrationException
    {
        $base = parent::toException($response, $method, $path);

        // Jubelio answers wrong credentials with a 500; they stay wrong (INT-JB-01: do not retry endlessly).
        $retryable = $base->retryable && $base->providerCode !== JubelioApiException::INVALID_CREDENTIALS;

        return new JubelioApiException(
            $base->service, $base->method, $base->path, $base->httpStatus,
            $retryable, $base->retryAfter, $base->providerCode, $base->providerMessage,
        );
    }

    private function baseRequest(): PendingRequest
    {
        return Http::baseUrl($this->config['base_url'])
            ->timeout($this->config['timeout'])
            ->acceptJson()
            ->asJson();
    }
}
