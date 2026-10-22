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
     * INT-JB-02 · Item categories, flat; `parent_id` 0 marks a root.
     *
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        $json = $this->get('/inventory/categories/item-categories/')->json();

        return $json['data'] ?? $json ?? [];
    }

    /**
     * INT-JB-03 · One page of item groups with their variants. Bundles are not
     * in this list: they come from bundles().
     *
     * @return array{data: list<array<string, mixed>>, totalCount: int}
     */
    public function items(int $page, int $pageSize = 200, bool $newestFirst = false): array
    {
        return $this->page('/inventory/items/', $page, $pageSize, $newestFirst);
    }

    /**
     * INT-JB-03 · One page of bundle item groups, same shape as items().
     *
     * @return array{data: list<array<string, mixed>>, totalCount: int}
     */
    public function bundles(int $page, int $pageSize = 200, bool $newestFirst = false): array
    {
        return $this->page('/inventory/item-bundles/', $page, $pageSize, $newestFirst);
    }

    /**
     * INT-JB-04 · Item group detail, or null when Jubelio no longer has it.
     *
     * @return array<string, mixed>|null
     */
    public function itemGroup(int $itemGroupId): ?array
    {
        try {
            $json = $this->get("/inventory/items/group/{$itemGroupId}")->json();
        } catch (JubelioApiException $e) {
            if ($e->httpStatus === 404) {
                return null;
            }

            throw $e;
        }

        return is_array($json) && isset($json['item_group_id']) ? $json : null;
    }

    /**
     * INT-JB-05 · One page of SKUs with their package weight. The trailing
     * slash is required: `/variations` answers 404.
     *
     * @return array{data: list<array<string, mixed>>, totalCount: int}
     */
    public function variations(int $page, int $pageSize = 200): array
    {
        return $this->page('/variations/', $page, $pageSize);
    }

    /**
     * INT-JB-06 · One page of stock per warehouse, optionally searched by SKU
     * (the search also returns similar SKUs; callers match item_code exactly).
     *
     * @return array{data: list<array<string, mixed>>, totalCount: int}
     */
    public function inventory(int $page, int $pageSize = 200, ?string $search = null): array
    {
        $query = ['page' => $page, 'pageSize' => $pageSize];

        if ($search !== null) {
            $query['q'] = $search;
        }

        $json = $this->get('/inventory/', $query)->json();

        return ['data' => $json['data'] ?? [], 'totalCount' => (int) ($json['totalCount'] ?? 0)];
    }

    /**
     * INT-JB-08 · Region lists, one level at a time; a parent Jubelio does not
     * know gives an empty list. No slash before the query string: Jubelio
     * answers `/region/cities/?province_id=` with 404.
     *
     * @param  'provinces'|'cities'|'districts'|'subdistricts'  $level
     * @param  array<string, string>  $parent  e.g. ['province_id' => '32']
     * @return list<array<string, mixed>>
     */
    public function regions(string $level, array $parent = []): array
    {
        try {
            $json = $this->get("/region/{$level}", $parent)->json();
        } catch (JubelioApiException $e) {
            if ($e->httpStatus === 404) {
                return [];
            }

            throw $e;
        }

        return is_array($json) ? ($json['data'] ?? $json) : [];
    }

    /**
     * INT-JB-09 · A customer contact with exactly this email, if Jubelio has one (a search by `q`
     * also returns similar emails).
     *
     * @return array<string, mixed>|null
     */
    public function findCustomerByEmail(string $email): ?array
    {
        $json = $this->get('/contacts/customers/', ['q' => $email, 'page' => 1, 'pageSize' => 10])->json();
        $rows = is_array($json) ? ($json['data'] ?? $json) : [];

        foreach ($rows as $row) {
            if (is_array($row) && strcasecmp((string) ($row['email'] ?? ''), $email) === 0) {
                return $row;
            }
        }

        return null;
    }

    /**
     * INT-JB-09 · A customer contact with this phone number, if Jubelio has one. Jubelio allows one contact per
     * email or phone, so a number the website did not register itself may already belong to a contact. The search
     * by `q` also returns similar numbers, so the digits are compared (08… and +62… are the same number).
     *
     * @return array<string, mixed>|null
     */
    public function findCustomerByPhone(string $phone): ?array
    {
        $digits = static fn (mixed $value): string => (string) preg_replace('/^62/', '0', (string) preg_replace('/\D+/', '', (string) $value));
        $wanted = $digits($phone);

        if ($wanted === '') {
            return null;
        }

        $json = $this->get('/contacts/customers/', ['q' => $wanted, 'page' => 1, 'pageSize' => 10])->json();
        $rows = is_array($json) ? ($json['data'] ?? $json) : [];

        foreach ($rows as $row) {
            if (is_array($row) && ($digits($row['phone'] ?? '') === $wanted || $digits($row['mobile'] ?? '') === $wanted)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * INT-JB-09 · Creates the contact (`contact_id` 0) or updates it, and returns its id.
     *
     * @param  array<string, mixed>  $contact
     */
    public function saveContact(array $contact): int
    {
        return $this->idFrom($this->post('/contacts/', $contact), 'contact_id', 'POST', '/contacts/');
    }

    /**
     * INT-JB-10 · Creates an unpaid sales order and returns its id.
     *
     * @param  array<string, mixed>  $order
     */
    public function createSalesOrder(array $order): int
    {
        return $this->idFrom($this->post('/sales/orders/', $order), 'id', 'POST', '/sales/orders/');
    }

    /**
     * INT-JB-11 · Marks sales orders paid, so the warehouse starts on them. Safe to repeat.
     *
     * @param  list<int>  $ids
     */
    public function setSalesOrdersPaid(array $ids): void
    {
        $this->post('/sales/orders/set-as-paid', ['ids' => $ids]);
    }

    /**
     * INT-JB-12 · Cancels sales orders, which gives their stock back.
     *
     * @param  list<int>  $ids
     */
    public function cancelSalesOrders(array $ids): void
    {
        $this->post('/sales/orders/cancel/', ['ids' => $ids]);
    }

    /**
     * INT-JB-13 · One sales order, or null when Jubelio does not know it.
     *
     * @return array<string, mixed>|null
     */
    public function salesOrder(int $id): ?array
    {
        try {
            $json = $this->get("/sales/orders/{$id}")->json();
        } catch (JubelioApiException $e) {
            if ($e->httpStatus === 404) {
                return null;
            }

            throw $e;
        }

        return is_array($json) ? $json : null;
    }

    /**
     * INT-JB-13 · The website store's sales order with exactly this `ref_no`. The search also matches
     * text inside other numbers, so the result is checked for an exact match.
     *
     * @return array<string, mixed>|null
     */
    public function findSalesOrderByRefNo(string $refNo): ?array
    {
        $json = $this->get('/sales/v2/orders/', [
            'page' => 1,
            'page_size' => 10,
            'q' => $refNo,
            'store_id' => $this->config['store_id'],
        ])->json();

        foreach (is_array($json) ? ($json['data'] ?? []) : [] as $row) {
            if (is_array($row) && ($row['ref_no'] ?? null) === $refNo) {
                return $row;
            }
        }

        return null;
    }

    /**
     * The id Jubelio answers a create call with; anything else is not trusted.
     */
    private function idFrom(Response $response, string $key, string $method, string $path): int
    {
        $id = $response->json($key);

        if (! is_numeric($id) || (int) $id <= 0) {
            throw new JubelioApiException('jubelio', $method, $path, $response->status(), retryable: false,
                providerMessage: "Respons Jubelio tidak berisi {$key}.");
        }

        return (int) $id;
    }

    /**
     * @return array{data: list<array<string, mixed>>, totalCount: int}
     */
    private function page(string $path, int $page, int $pageSize, bool $newestFirst = false): array
    {
        $query = ['page' => $page, 'pageSize' => $pageSize];

        if ($newestFirst) {
            $query += ['sortBy' => 'last_modified', 'sortDirection' => 'DESC'];
        }

        $json = $this->get($path, $query)->json();

        return ['data' => $json['data'] ?? [], 'totalCount' => (int) ($json['totalCount'] ?? 0)];
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
