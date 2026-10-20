<?php

use App\Enums\PaymentStatus;
use App\Exceptions\IntegrationException;
use App\Services\Gapura\GapuraClient;
use App\Services\Gapura\GapuraSignatureException;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Sleep;
use Monolog\Handler\TestHandler;
use Psr\Http\Message\RequestInterface;

/*
| INT-GP-01 – INT-GP-03 and the WH-01 signature check, through DANA's official SDK. The SDK sends
| with a Guzzle client that the tests hand a mock handler, so nothing reaches DANA. Requests are
| signed with a throwaway key pair (tests/Pest.php gapuraKeys()).
*/

beforeEach(function () {
    Sleep::fake();

    $this->config = [
        'env' => 'sandbox',
        'client_id' => 'client-123',
        'merchant_id' => 'merchant-456',
        'private_key_path' => gapuraKeys('merchant')['private'],
        'dana_public_key_path' => gapuraKeys('dana')['public'],
        'origin' => 'https://toko.test',
        'mcc' => '5499',
        'notify_url' => null,
        'payment_duration' => 7200,
        'timeout' => 8,
    ];
    config(['app.url' => 'https://toko.test']);
    gapuraSent(reset: true);
});

/**
 * Requests the SDK has sent in this test (reset in beforeEach).
 *
 * @return ArrayObject<int, RequestInterface>
 */
function gapuraSent(bool $reset = false): ArrayObject
{
    static $sent = null;

    return $sent = $reset || $sent === null ? new ArrayObject : $sent;
}

/**
 * A GapuraClient whose SDK answers with the given responses in order; requests are kept in gapuraSent().
 *
 * @param  list<Response>  $responses
 */
function gapuraClient(array $responses, array $config = []): GapuraClient
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
        gapuraSent()->append($request);

        return $request;
    }));

    return new GapuraClient($config + test()->config, new Client(['handler' => $stack]));
}

function gapuraJson(string $fixture, int $status = 200): Response
{
    return new Response($status, ['Content-Type' => 'application/json'], fixtureContent("gapura/{$fixture}.json"));
}

function createGapuraOrder(GapuraClient $client, int $amount = 6935000, ?CarbonImmutable $expiresAt = null)
{
    return $client->createOrder('WEB-000123', $amount, $expiresAt ?? CarbonImmutable::now()->addMinutes(20), 'Pesanan WEB-000123', 'https://toko.test/checkout/finish?order=WEB-000123');
}

// --- Create Order (INT-GP-01) -------------------------------------------------------

test('Create Order returns the hosted checkout URL and DANA reference', function () {
    $order = createGapuraOrder(gapuraClient([gapuraJson('create-order-success')]));

    expect($order->referenceNo)->toBe('20261020111212800110166000000000001')
        ->and($order->partnerReferenceNo)->toBe('WEB-000123')
        ->and($order->checkoutUrl)->toStartWith('https://m.sandbox.dana.id/d/portal/cashier?ticket=');
});

test('Create Order posts the order the way DANA expects', function () {
    createGapuraOrder(gapuraClient([gapuraJson('create-order-success')]));

    $request = gapuraSent()[0];
    $body = json_decode((string) $request->getBody(), true);

    expect((string) $request->getUri())->toBe('https://api.sandbox.dana.id/payment-gateway/v1.0/debit/payment-host-to-host.htm')
        ->and($request->getMethod())->toBe('POST')
        ->and($body['partnerReferenceNo'])->toBe('WEB-000123')
        ->and($body['merchantId'])->toBe('merchant-456')
        ->and($body['amount'])->toBe(['value' => '6935000.00', 'currency' => 'IDR'])
        ->and($body['additionalInfo']['mcc'])->toBe('5499')
        ->and($body['additionalInfo']['order'])->toMatchArray(['orderTitle' => 'Pesanan WEB-000123', 'scenario' => 'REDIRECT'])
        ->and($body['additionalInfo']['envInfo']['sourcePlatform'])->toBe('IPG')
        ->and(collect($body['urlParams'])->pluck('url', 'type')->all())->toBe([
            'NOTIFICATION' => 'https://toko.test/v1.0/debit/notify',
            'PAY_RETURN' => 'https://toko.test/checkout/finish?order=WEB-000123',
        ]);
});

test('Create Order headers carry the partner id and a signature our public key verifies', function () {
    createGapuraOrder(gapuraClient([gapuraJson('create-order-success')]));

    $request = gapuraSent()[0];
    // DANA hashes the minified body: no whitespace, slashes unescaped.
    $minified = json_encode(json_decode((string) $request->getBody()), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $timestamp = $request->getHeaderLine('X-TIMESTAMP');
    $stringToSign = 'POST:/payment-gateway/v1.0/debit/payment-host-to-host.htm:'.hash('sha256', $minified).':'.$timestamp;

    expect($request->getHeaderLine('X-PARTNER-ID'))->toBe('client-123')
        ->and($request->getHeaderLine('ORIGIN'))->toBe('https://toko.test')
        ->and($timestamp)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+07:00$/')
        ->and($request->getHeaderLine('X-EXTERNAL-ID'))->not->toBe('')
        ->and(openssl_verify(
            $stringToSign,
            base64_decode($request->getHeaderLine('X-SIGNATURE')),
            file_get_contents(gapuraKeys('merchant')['public']),
            OPENSSL_ALGO_SHA256,
        ))->toBe(1);
});

test('a configured notify URL is used as the NOTIFICATION url', function () {
    createGapuraOrder(gapuraClient([gapuraJson('create-order-success')], ['notify_url' => 'https://abc.ngrok-free.app/v1.0/debit/notify']));

    $urls = collect(json_decode((string) gapuraSent()[0]->getBody(), true)['urlParams'])->pluck('url', 'type');

    expect($urls['NOTIFICATION'])->toBe('https://abc.ngrok-free.app/v1.0/debit/notify');
});

test('the sandbox deadline is capped at 30 minutes, production keeps the full time', function () {
    $twoHours = CarbonImmutable::now()->addHours(2);
    $minutesAhead = fn (string $validUpTo) => CarbonImmutable::now()->diffInMinutes(CarbonImmutable::parse($validUpTo));

    createGapuraOrder(gapuraClient([gapuraJson('create-order-success')]), expiresAt: $twoHours);
    $sandbox = json_decode((string) gapuraSent()[0]->getBody(), true)['validUpTo'];

    gapuraSent(reset: true);
    createGapuraOrder(gapuraClient([gapuraJson('create-order-success')], ['env' => 'production']), expiresAt: $twoHours);
    $production = json_decode((string) gapuraSent()[0]->getBody(), true)['validUpTo'];

    expect($sandbox)->toEndWith('+07:00')
        ->and($minutesAhead($sandbox))->toBeBetween(29, 30)
        ->and($minutesAhead($production))->toBeBetween(119, 120);
});

test('production calls api.saas.dana.id', function () {
    createGapuraOrder(gapuraClient([gapuraJson('create-order-success')], ['env' => 'production']));

    expect(gapuraSent()[0]->getUri()->getHost())->toBe('api.saas.dana.id');
});

test('the SDK refusing our own request is not retried', function () {
    $e = catchIntegration(fn () => createGapuraOrder(gapuraClient([gapuraJson('create-order-success')], ['mcc' => ''])));

    expect($e->retryable)->toBeFalse()->and($e->httpStatus)->toBeNull();
    expect(gapuraSent())->toBeEmpty();
});

test('a missing private key file fails clearly before any request', function () {
    expect(fn () => createGapuraOrder(gapuraClient([], ['private_key_path' => '/tidak/ada.pem'])))
        ->toThrow(RuntimeException::class, 'GAPURA_PRIVATE_KEY_PATH');
});

// --- Errors (INT-HDL) ---------------------------------------------------------------

test('a 4xx is not retried and keeps DANA response code and message', function () {
    $e = catchIntegration(fn () => createGapuraOrder(gapuraClient([gapuraJson('create-order-error-invalid-field', 400)])));

    expect($e->retryable)->toBeFalse()
        ->and($e->providerCode)->toBe('4005402')
        ->and($e->detail())->toBe('gapura POST /payment-gateway/v1.0/debit/payment-host-to-host.htm → HTTP 400 [4005402] Invalid Mandatory Field');
    expect(gapuraSent())->toHaveCount(1);
});

test('a 5xx and a 429 are retryable', function (int $status) {
    $response = new Response($status, [], '{"responseCode":"5005401","responseMessage":"Internal Server Error"}');

    expect(catchIntegration(fn () => createGapuraOrder(gapuraClient([$response])))->retryable)->toBeTrue();
})->with([500, 503, 429]);

test('a 429 asks for a minute of waiting', function () {
    $e = catchIntegration(fn () => createGapuraOrder(gapuraClient([gapuraJson('error-too-many-requests', 429)])));

    expect($e->retryAfter)->toBe(60)->and($e->providerCode)->toBe('4295400');
});

test('inside a customer request a transient failure is retried once with the same order number', function () {
    $client = gapuraClient([new Response(503), gapuraJson('create-order-success')])->withinCustomerRequest();

    expect(createGapuraOrder($client)->partnerReferenceNo)->toBe('WEB-000123');
    expect(gapuraSent())->toHaveCount(2)
        ->and(json_decode((string) gapuraSent()[1]->getBody(), true)['partnerReferenceNo'])->toBe('WEB-000123');
    Sleep::assertSleptTimes(1);
});

test('a retry inside a customer request gives up after one more attempt', function () {
    $client = gapuraClient([new Response(503), new Response(503), gapuraJson('create-order-success')])->withinCustomerRequest();

    expect(catchIntegration(fn () => createGapuraOrder($client))->httpStatus)->toBe(503);
    expect(gapuraSent())->toHaveCount(2);
});

test('a connection failure is retryable and has no HTTP status', function () {
    $failure = new ConnectException('cURL error 28: timed out', new Request('POST', 'https://api.sandbox.dana.id'));

    $e = catchIntegration(fn () => createGapuraOrder(gapuraClient([$failure])));

    expect($e->retryable)->toBeTrue()->and($e->httpStatus)->toBeNull();
});

test('a 200 without the success code is not trusted', function () {
    $odd = new Response(200, [], '{"responseCode":"2005401","responseMessage":"Odd","webRedirectUrl":"https://x.test"}');

    $e = catchIntegration(fn () => createGapuraOrder(gapuraClient([$odd])));

    expect($e->retryable)->toBeFalse()->and($e->providerCode)->toBe('2005401');
});

test('calls are logged without bodies or credentials', function () {
    config(['logging.channels.integrations' => ['driver' => 'monolog', 'handler' => TestHandler::class]]);

    createGapuraOrder(gapuraClient([gapuraJson('create-order-success')]));

    $record = integrationLog()->getRecords()[0];
    expect($record['message'])->toStartWith('gapura POST /payment-gateway/v1.0/debit/payment-host-to-host.htm 200')
        ->and(json_encode($record['context']))->not->toContain('6935000')->not->toContain('merchant-456');
});

// --- Query Payment (INT-GP-02) ------------------------------------------------------

test('Query Payment maps DANA status 00 to PAID with payment details', function () {
    $payment = gapuraClient([gapuraJson('query-payment-paid')])->queryPayment('WEB-000123');

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->statusCode)->toBe('00')
        ->and($payment->amount)->toBe(6935000)
        ->and($payment->method)->toBe('VIRTUAL_ACCOUNT')
        ->and($payment->channel)->toBe('VIRTUAL_ACCOUNT_BCA')
        ->and($payment->paidAt->toIso8601String())->toBe('2026-10-20T10:42:10+07:00');

    $body = json_decode((string) gapuraSent()[0]->getBody(), true);
    expect((string) gapuraSent()[0]->getUri())->toBe('https://api.sandbox.dana.id/payment-gateway/v1.0/debit/status.htm')
        ->and($body)->toMatchArray(['originalPartnerReferenceNo' => 'WEB-000123', 'serviceCode' => '54', 'merchantId' => 'merchant-456']);
});

test('Query Payment maps status 01 to PENDING', function () {
    $payment = gapuraClient([gapuraJson('query-payment-pending')])->queryPayment('WEB-000124');

    expect($payment->status)->toBe(PaymentStatus::Pending)->and($payment->paidAt)->toBeNull();
});

test('Query Payment status codes map to local payment statuses', function (string $code, PaymentStatus $expected) {
    $response = fixture('gapura/query-payment-pending.json');
    $response['latestTransactionStatus'] = $code;

    $payment = gapuraClient([new Response(200, [], json_encode($response))])->queryPayment('WEB-000124');

    expect($payment->status)->toBe($expected);
})->with([
    '00 paid' => ['00', PaymentStatus::Paid],
    '02 paying' => ['02', PaymentStatus::Paid],
    '01 initiated' => ['01', PaymentStatus::Pending],
    '05 closed' => ['05', PaymentStatus::Expired],
    '07 not found' => ['07', PaymentStatus::Expired],
]);

test('Query Payment refuses a status code it does not know', function () {
    $response = fixture('gapura/query-payment-pending.json');
    $response['latestTransactionStatus'] = '99';

    expect(fn () => gapuraClient([new Response(200, [], json_encode($response))])->queryPayment('WEB-000124'))
        ->toThrow(IntegrationException::class);
});

// --- Cancel Order (INT-GP-03) -------------------------------------------------------

test('Cancel Order posts the original order number and the reason', function () {
    gapuraClient([gapuraJson('cancel-order-success')])->cancelOrder('WEB-000123', 'Dibatalkan pelanggan');

    $body = json_decode((string) gapuraSent()[0]->getBody(), true);
    expect((string) gapuraSent()[0]->getUri())->toBe('https://api.sandbox.dana.id/payment-gateway/v1.0/debit/cancel.htm')
        ->and($body)->toBe(['originalPartnerReferenceNo' => 'WEB-000123', 'merchantId' => 'merchant-456', 'reason' => 'Dibatalkan pelanggan']);
});

test('Cancel Order treats "request in progress" as accepted', function () {
    $inProgress = new Response(202, [], '{"responseCode":"2025700","responseMessage":"Request In Progress"}');

    expect(gapuraClient([$inProgress])->cancelOrder('WEB-000123'))->toBeNull();
});

test('Cancel Order surfaces DANA refusing it', function () {
    $expired = new Response(403, [], '{"responseCode":"4035700","responseMessage":"Transaction Expired"}');

    $e = catchIntegration(fn () => gapuraClient([$expired])->cancelOrder('WEB-000123'));

    expect($e->retryable)->toBeFalse()->and($e->providerCode)->toBe('4035700');
});

// --- Finish Notify signature (WH-01) -----------------------------------------------

function gapuraNotifyClient(): GapuraClient
{
    // Production mode makes the SDK use DANA's key from our file instead of the one built into it.
    return new GapuraClient(['env' => 'production', 'dana_public_key_path' => gapuraKeys('dana')['public']] + test()->config);
}

test('a Finish Notify signed with the matching key is verified and parsed', function () {
    $body = fixtureContent('gapura/finish-notify-paid.json');

    $notification = gapuraNotifyClient()->parseNotification($body, gapuraNotifyHeaders($body));

    expect($notification->partnerReferenceNo)->toBe('WEB-000123')
        ->and($notification->referenceNo)->toBe('20261020111212800110166000000000001')
        ->and($notification->statusCode)->toBe('00')
        ->and($notification->status)->toBe(PaymentStatus::Paid)
        ->and($notification->amount)->toBe(6935000)
        ->and($notification->method)->toBe('VIRTUAL_ACCOUNT')
        ->and($notification->channel)->toBe('VIRTUAL_ACCOUNT_BCA')
        ->and($notification->paidAt->toIso8601String())->toBe('2026-10-20T10:42:10+07:00');
});

test('a cancelled Finish Notify keeps the closed reason', function () {
    $body = fixtureContent('gapura/finish-notify-cancelled.json');

    $notification = gapuraNotifyClient()->parseNotification($body, gapuraNotifyHeaders($body));

    expect($notification->status)->toBe(PaymentStatus::Expired)
        ->and($notification->statusCode)->toBe('05')
        ->and($notification->closedReason)->toBe('EXPIRED');
});

test('a pretty-printed body with a nested JSON string still verifies, as in DANA\'s own sample', function () {
    $body = json_encode(json_decode(fixtureContent('gapura/finish-notify-cancelled.json')), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $notification = gapuraNotifyClient()->parseNotification($body, gapuraNotifyHeaders($body));

    expect($notification->statusCode)->toBe('05')->and($notification->closedReason)->toBe('EXPIRED');
});

test('a changed body fails verification', function () {
    $body = fixtureContent('gapura/finish-notify-paid.json');
    $headers = gapuraNotifyHeaders($body);

    expect(fn () => gapuraNotifyClient()->parseNotification(str_replace('6935000.00', '1.00', $body), $headers))
        ->toThrow(GapuraSignatureException::class);
});

test('a signature from another key fails verification', function () {
    $body = fixtureContent('gapura/finish-notify-paid.json');

    expect(fn () => gapuraNotifyClient()->parseNotification($body, gapuraNotifyHeaders($body, gapuraKeys('someone-else')['private'])))
        ->toThrow(GapuraSignatureException::class);
});

test('a Finish Notify without signature headers is rejected', function () {
    expect(fn () => gapuraNotifyClient()->parseNotification(fixtureContent('gapura/finish-notify-paid.json'), []))
        ->toThrow(GapuraSignatureException::class, 'Missing X-SIGNATURE');
});

test('a signature made for another timestamp fails verification', function () {
    $body = fixtureContent('gapura/finish-notify-paid.json');
    $headers = gapuraNotifyHeaders($body);
    $headers['X-TIMESTAMP'] = '2026-10-20T10:43:00+07:00';

    expect(fn () => gapuraNotifyClient()->parseNotification($body, $headers))->toThrow(GapuraSignatureException::class);
});
