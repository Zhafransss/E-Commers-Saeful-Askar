<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SyncLogResource;
use App\Jobs\Jubelio\RunManualSyncJob;
use App\Models\SyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * Contract: docs/api/admin.md
 */
class SyncController extends Controller
{
    private const LABELS = ['categories' => 'kategori', 'products' => 'produk', 'stock' => 'stok', 'full' => 'penuh'];

    /**
     * ADM-13 · Jalankan sinkron manual
     */
    public function store(Request $request): JsonResponse
    {
        $input = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::LABELS))],
            'full' => ['nullable', 'boolean'],
        ]);
        $type = $input['type'];

        // One sync of each kind at a time; `full` needs all three kinds free.
        $kinds = $type === 'full' ? ['categories', 'products', 'stock'] : [$type];
        $locks = [];

        foreach ($kinds as $kind) {
            $lock = Cache::lock("sync:{$kind}", 1800);

            if (! $lock->get()) {
                collect($locks)->each(fn ($held) => Cache::restoreLock($held['name'], $held['owner'])->release());

                throw $this->alreadyRunning($kind);
            }

            $locks[] = ['name' => "sync:{$kind}", 'owner' => $lock->owner()];
        }

        $log = SyncLog::start(SyncType::from($type === 'full' ? 'categories' : $type), SyncTrigger::Manual);
        RunManualSyncJob::dispatch($type, (bool) ($input['full'] ?? false), $log->id, $locks);

        return response()->json([
            'data' => [
                'sync_log_id' => $log->id,
                'type' => $type,
                'status' => 'running',
                'started_at' => $log->started_at,
            ],
            'message' => 'Sinkron '.self::LABELS[$type].' dimulai.',
        ], 202);
    }

    /**
     * ADM-14 · Riwayat sinkron
     */
    public function logs(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(SyncType::class)],
            'status' => ['nullable', Rule::enum(SyncStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $logs = SyncLog::query()
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest('started_at')->latest('id')
            ->paginate($request->integer('per_page', 20));

        return SyncLogResource::collection($logs)->response();
    }

    private function alreadyRunning(string $kind): ApiException
    {
        $running = SyncLog::where('type', $kind)->where('status', SyncStatus::Running->value)->latest('id')->first();
        $since = $running?->started_at?->format('H.i');

        return new ApiException(
            'Sinkron '.self::LABELS[$kind].' masih berjalan'.($since ? " sejak {$since}" : '').'. Tunggu hingga selesai.',
            'SYNC_ALREADY_RUNNING',
            409,
            extra: array_filter(['sync_log_id' => $running?->id]),
        );
    }
}
