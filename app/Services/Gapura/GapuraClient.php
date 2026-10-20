<?php

namespace App\Services\Gapura;

use App\Exceptions\IntegrationException;
use App\Services\IntegrationClient;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Dana\ApiException as DanaApiException;
use Dana\Configuration;
use Dana\PaymentGateway\v1\Api\PaymentGatewayApi;
use Dana\PaymentGateway\v1\Model\CancelOrderRequest;
use Dana\PaymentGateway\v1\Model\CreateOrderByRedirectAdditionalInfo;
use Dana\PaymentGateway\v1\Model\CreateOrderByRedirectRequest;
use Dana\PaymentGateway\v1\Model\EnvInfo;
use Dana\PaymentGateway\v1\Model\Money;
use Dana\PaymentGateway\v1\Model\OrderRedirectObject;
use Dana\PaymentGateway\v1\Model\QueryPaymentRequest;
use Dana\PaymentGateway\v1\Model\UrlParam;
use Dana\Webhook\v1\FinishNotifyRequestAdditionalInfo;
use Dana\Webhook\v1\WebhookParser;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;

/**
 * The only class allowed to call DANA's Gapura Payment Gateway (INT-GP-01 – INT-GP-03) and to
 * verify its Finish Notify webhook (WH-01). It wraps DANA's official SDK (danaid/dana-php), which
 * signs every request with our private key (RSA-2048 / SHA-256, SNAP) and verifies DANA's webhook
 * with DANA's public key.
 *
 * The SDK does its own HTTP, so failures arrive as Dana\ApiException and are turned into
 * IntegrationException here, with the same retry rules as the other clients (INT-HDL): 5xx, 429,
 * and connection errors are retryable, other 4xx are not.
 */
class GapuraClient extends IntegrationClient
{
    /** DANA requires this path for the notification URL, and signs the webhook with it. */
    public const NOTIFY_PATH = '/v1.0/debit/notify';

    /** SNAP service code of Create Order; Query Payment asks for the code of the original call. */
    private const SERVICE_CODE_CREATE_ORDER = '54';

    private const SUCCESS_CREATE = '2005400';

    private const SUCCESS_CANCEL = ['2005700', '2025700'];

    private const PATH_CREATE = '/payment-gateway/v1.0/debit/payment-host-to-host.htm';

    private const PATH_QUERY = '/payment-gateway/v1.0/debit/status.htm';

    private const PATH_CANCEL = '/payment-gateway/v1.0/debit/cancel.htm';

    private ?PaymentGatewayApi $api = null;

    private ?WebhookParser $parser = null;

    /**
     * @param  array<string, mixed>  $config  config('gapura')
     * @param  ClientInterface|null  $http  Guzzle client the SDK sends with; tests pass one with a mock handler
     */
    public function __construct(private readonly array $config, private readonly ?ClientInterface $http = null) {}

    protected function service(): string
    {
        return 'gapura';
    }

    public function isProduction(): bool
    {
        return $this->config['env'] === 'production';
    }

    /**
     * INT-GP-01. Creates the order at DANA and returns the hosted checkout URL to redirect the
     * customer to. `partnerReferenceNo` is our order number: asking again with the same number and
     * the same data returns the same order, so a retry after a timeout cannot create a second one.
     */
    public function createOrder(string $orderNumber, int $amount, CarbonInterface $expiresAt, string $title, string $returnUrl): GapuraOrder
    {
        $deadline = $this->deadline($expiresAt);
        $request = new CreateOrderByRedirectRequest([
            'partnerReferenceNo' => $orderNumber,
            'merchantId' => $this->config['merchant_id'],
            'amount' => $this->money($amount),
            'validUpTo' => $deadline->format('Y-m-d\TH:i:sP'),
            'urlParams' => [
                new UrlParam(['url' => $this->notifyUrl(), 'type' => 'NOTIFICATION', 'isDeeplink' => 'N']),
                new UrlParam(['url' => $returnUrl, 'type' => 'PAY_RETURN', 'isDeeplink' => 'N']),
            ],
            'additionalInfo' => new CreateOrderByRedirectAdditionalInfo([
                'mcc' => $this->config['mcc'],
                'envInfo' => new EnvInfo(['sourcePlatform' => 'IPG', 'terminalType' => 'SYSTEM']),
                'order' => new OrderRedirectObject(['orderTitle' => $title, 'scenario' => 'REDIRECT']),
            ]),
        ]);

        $response = $this->call('POST', self::PATH_CREATE, fn () => $this->api()->createOrder($request));

        if ($response->getResponseCode() !== self::SUCCESS_CREATE || blank($response->getWebRedirectUrl())) {
            throw $this->unexpected('POST', self::PATH_CREATE, $response->getResponseCode(), $response->getResponseMessage());
        }

        return new GapuraOrder(
            referenceNo: (string) $response->getReferenceNo(),
            partnerReferenceNo: (string) $response->getPartnerReferenceNo(),
            checkoutUrl: $response->getWebRedirectUrl(),
            expiresAt: $deadline,
        );
    }

    /**
     * INT-GP-02. Where the order stands at DANA, for the cases where the webhook did not arrive.
     * An order DANA does not know answers 404, which reaches the caller as a non-retryable error.
     */
    public function queryPayment(string $orderNumber): GapuraPayment
    {
        $request = new QueryPaymentRequest([
            'originalPartnerReferenceNo' => $orderNumber,
            'serviceCode' => self::SERVICE_CODE_CREATE_ORDER,
            'merchantId' => $this->config['merchant_id'],
        ]);

        $response = $this->call('POST', self::PATH_QUERY, fn () => $this->api()->queryPayment($request));
        $code = (string) $response->getLatestTransactionStatus();
        $status = GapuraStatus::toPaymentStatus($code)
            ?? throw $this->unexpected('POST', self::PATH_QUERY, $response->getResponseCode(), "latestTransactionStatus {$code}");

        $view = $response->getAdditionalInfo()?->getPaymentViews()[0] ?? null;
        $option = $view?->getPayOptionInfos()[0] ?? null;

        return new GapuraPayment(
            status: $status,
            statusCode: $code,
            referenceNo: $response->getOriginalReferenceNo(),
            amount: $this->rupiah($response->getAmount()?->getValue()),
            paidAt: $this->time($response->getPaidTime()),
            method: $option?->getPayMethod(),
            channel: $option?->getPayOption(),
        );
    }

    /**
     * INT-GP-03. Closes an unpaid order so it can no longer be paid. DANA answers `2005700`
     * (cancelled) or `2025700` (in progress); both count as accepted.
     */
    public function cancelOrder(string $orderNumber, ?string $reason = null): void
    {
        $request = new CancelOrderRequest(array_filter([
            'originalPartnerReferenceNo' => $orderNumber,
            'merchantId' => $this->config['merchant_id'],
            'reason' => $reason,
        ]));

        $response = $this->call('POST', self::PATH_CANCEL, fn () => $this->api()->cancelOrder($request));

        if (! in_array($response->getResponseCode(), self::SUCCESS_CANCEL, true)) {
            throw $this->unexpected('POST', self::PATH_CANCEL, $response->getResponseCode(), $response->getResponseMessage());
        }
    }

    /**
     * WH-01. Verifies X-SIGNATURE with DANA's public key against `POST /v1.0/debit/notify`, the
     * path DANA signs, and returns the notification. Anything that does not verify throws
     * GapuraSignatureException and must not be trusted.
     *
     * @param  array<string, string>  $headers  Request headers; only X-SIGNATURE and X-TIMESTAMP are read
     */
    public function parseNotification(string $body, array $headers): GapuraNotification
    {
        try {
            $notify = $this->parser()->parseWebhook('POST', self::NOTIFY_PATH, $headers, $body);
        } catch (Throwable $e) {
            throw new GapuraSignatureException($e->getMessage(), previous: $e);
        }

        $info = $notify->getAdditionalInfo();
        $payment = $info?->getPaymentInfo();
        $option = $payment?->getPayOptionInfos()[0] ?? null;
        $code = (string) $notify->getLatestTransactionStatus();

        return new GapuraNotification(
            partnerReferenceNo: (string) $notify->getOriginalPartnerReferenceNo(),
            referenceNo: (string) $notify->getOriginalReferenceNo(),
            statusCode: $code,
            status: GapuraStatus::toPaymentStatus($code),
            amount: $this->rupiah($notify->getAmount()?->getValue()),
            paidAt: $this->time($payment?->getPaidTime()),
            method: $option?->getPayMethod(),
            channel: $option?->getPayOption(),
            closedReason: $this->closedReason($info),
        );
    }

    /**
     * Runs one SDK call with logging and INT-HDL error handling; a retryable failure is tried
     * again once when the call is made while a customer waits (see withinCustomerRequest()).
     *
     * @template T
     *
     * @param  Closure(): T  $call
     * @return T
     */
    private function call(string $method, string $path, Closure $call): mixed
    {
        $attempt = 0;

        while (true) {
            $startedAt = hrtime(true);

            try {
                $result = $call();
                $this->log('info', $method, $path, 200, $startedAt);

                return $result;
            } catch (DanaApiException $e) {
                $exception = $this->toIntegrationException($e, $method, $path);
                $this->log('warning', $method, $path, $exception->httpStatus, $startedAt, ['provider_code' => $exception->providerCode]);

                if (! $exception->retryable || $attempt >= $this->retries) {
                    throw $exception;
                }

                $attempt++;
                Sleep::for(200)->milliseconds();
            }
        }
    }

    /**
     * 5xx and 429 are retryable, as DANA says to retry those with the same request; other 4xx
     * are not. The SDK reports a connection failure as "[<cURL error>] <message>" with no HTTP
     * status (retryable). Any other exception without a status is the SDK refusing our own
     * request before sending it (its validation), which another attempt cannot fix.
     */
    private function toIntegrationException(DanaApiException $e, string $method, string $path): IntegrationException
    {
        $status = $e->getCode() >= 100 ? $e->getCode() : null;
        $body = json_decode((string) $e->getResponseBody(), true);
        $connectionFailure = $status === null && str_starts_with($e->getMessage(), '[');

        return new IntegrationException(
            service: $this->service(),
            method: $method,
            path: $path,
            httpStatus: $status,
            retryable: $connectionFailure || ($status !== null && ($status >= 500 || $status === 429)),
            retryAfter: $status === 429 ? 60 : null,
            providerCode: $this->stringOrNull($body['responseCode'] ?? null),
            providerMessage: $this->stringOrNull($body['responseMessage'] ?? null) ?? ($status === null ? $e->getMessage() : null),
        );
    }

    /**
     * DANA answered 2xx but not with the success code we need.
     */
    private function unexpected(string $method, string $path, ?string $code, ?string $message): IntegrationException
    {
        return new IntegrationException(
            service: $this->service(),
            method: $method,
            path: $path,
            httpStatus: 200,
            retryable: false,
            providerCode: $this->stringOrNull($code),
            providerMessage: $this->stringOrNull($message),
        );
    }

    private function api(): PaymentGatewayApi
    {
        if ($this->api !== null) {
            return $this->api;
        }

        $privateKey = (string) $this->config['private_key_path'];
        $privateKey = str_starts_with($privateKey, '/') ? $privateKey : base_path($privateKey);

        if ($this->config['private_key_path'] === null || ! is_file($privateKey)) {
            throw new RuntimeException('GAPURA_PRIVATE_KEY_PATH tidak menunjuk ke file private key.');
        }

        $this->exportEnvironment();

        $sdk = new Configuration;
        $sdk->setApiKey('PRIVATE_KEY_PATH', $privateKey);
        $sdk->setApiKey('X_PARTNER_ID', (string) $this->config['client_id']);
        $sdk->setApiKey('ORIGIN', (string) $this->config['origin']);
        $sdk->setApiKey('DANA_ENV', $this->config['env']);
        $sdk->setApiKey('ENV', $this->config['env']);
        // The SDK picks its host from the process environment when constructed, so set it explicitly.
        $sdk->setHost($this->isProduction() ? 'https://api.saas.dana.id' : 'https://api.sandbox.dana.id');

        $http = $this->http ?? new Client(['timeout' => $this->config['timeout'], 'connect_timeout' => $this->config['timeout']]);

        return $this->api = new PaymentGatewayApi($http, $sdk);
    }

    private function parser(): WebhookParser
    {
        if ($this->parser !== null) {
            return $this->parser;
        }

        // The parser reads DANA_ENV from the process environment: sandbox uses the key built into
        // the SDK, production needs DANA's production public key from our own file.
        $this->exportEnvironment();

        return $this->parser = WebhookParser::create(null, $this->config['dana_public_key_path'] ?: null);
    }

    /**
     * The SDK looks at DANA_ENV in the process environment, not at our config, for its sandbox-only
     * rules (the 30-minute validUpTo limit, the built-in webhook key). Keep it equal to GAPURA_ENV
     * however the .env file was loaded.
     */
    private function exportEnvironment(): void
    {
        putenv('DANA_ENV='.$this->config['env']);
        putenv('ENV='.$this->config['env']);
    }

    private function notifyUrl(): string
    {
        return filled($this->config['notify_url'])
            ? $this->config['notify_url']
            : rtrim((string) config('app.url'), '/').self::NOTIFY_PATH;
    }

    /**
     * DANA wants the amount as a string with two decimals: 6935000 → "6935000.00".
     */
    private function money(int $amount): Money
    {
        return new Money(['value' => number_format($amount, 2, '.', ''), 'currency' => 'IDR']);
    }

    /** "6935000.00" → 6935000. */
    private function rupiah(?string $value): ?int
    {
        return $value === null || $value === '' ? null : (int) round((float) $value);
    }

    /**
     * DANA sends the reason inside `additionalInfo.extendInfo`, a JSON object written as a string:
     * "{\"closedReason\":\"EXPIRED\"}". The SDK leaves it as that string.
     */
    private function closedReason(?FinishNotifyRequestAdditionalInfo $info): ?string
    {
        $extend = json_decode((string) $info?->getExtendInfo(), true);

        return $this->stringOrNull(is_array($extend) ? ($extend['closedReason'] ?? null) : null)
            ?? $this->stringOrNull($info?->getExtendInfoClosedReason());
    }

    private function time(?string $value): ?CarbonImmutable
    {
        return $value === null || $value === '' ? null : CarbonImmutable::parse($value);
    }

    /**
     * `validUpTo` in Jakarta time. The sandbox refuses anything more than 30 minutes ahead.
     */
    private function deadline(CarbonInterface $expiresAt): CarbonImmutable
    {
        $latest = $this->isProduction() ? null : CarbonImmutable::now()->addMinutes(30)->subSeconds(5);
        $expiry = CarbonImmutable::instance($expiresAt);

        if ($latest !== null && $expiry->greaterThan($latest)) {
            $expiry = $latest;
        }

        return $expiry->setTimezone('Asia/Jakarta');
    }
}
