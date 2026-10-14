<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Converts framework exceptions into the API Contract error format
 * (docs/api/README.md: "Format response gagal" and "Kode error umum").
 */
class ApiExceptionRenderer
{
    /** @var array<int, array{0: string, 1: string}> HTTP status => [code, message] */
    private const HTTP_ERRORS = [
        400 => ['BAD_REQUEST', 'Format permintaan tidak valid.'],
        401 => ['UNAUTHENTICATED', 'Silakan masuk terlebih dahulu.'],
        403 => ['FORBIDDEN', 'Anda tidak punya akses ke halaman ini.'],
        404 => ['NOT_FOUND', 'Data tidak ditemukan.'],
        405 => ['METHOD_NOT_ALLOWED', 'Metode permintaan tidak diizinkan.'],
        409 => ['CONFLICT', 'Permintaan bentrok dengan status data saat ini.'],
        419 => ['CSRF_TOKEN_MISMATCH', 'Sesi keamanan kedaluwarsa. Muat ulang halaman lalu coba lagi.'],
        429 => ['TOO_MANY_REQUESTS', 'Terlalu banyak permintaan. Coba lagi beberapa saat.'],
        503 => ['SERVICE_UNAVAILABLE', 'Website sedang dalam perawatan. Coba lagi beberapa saat.'],
    ];

    public static function shouldRender(Request $request): bool
    {
        return $request->is('api/*', 'webhooks/*') || $request->expectsJson();
    }

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! self::shouldRender($request) || $e instanceof ApiException) {
            // ApiException renders itself; non-JSON requests keep Laravel's HTML pages.
            return null;
        }

        if ($e instanceof ValidationException) {
            return $this->json(422, 'VALIDATION_ERROR', 'Data yang dikirim tidak valid.', ['errors' => $e->errors()]);
        }

        if ($e instanceof AuthenticationException) {
            return $this->json(401, ...self::HTTP_ERRORS[401]);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            [$code, $message] = self::HTTP_ERRORS[$status] ?? ['SERVER_ERROR', 'Terjadi kesalahan pada server.'];

            return $this->json($status, $code, $message, headers: $e->getHeaders());
        }

        $extra = config('app.debug')
            ? ['debug' => ['exception' => $e::class, 'message' => $e->getMessage()]]
            : [];

        return $this->json(500, 'SERVER_ERROR', 'Terjadi kesalahan pada server.', $extra);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $headers
     */
    private function json(int $status, string $code, string $message, array $extra = [], array $headers = []): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code] + $extra, $status, $headers);
    }
}
