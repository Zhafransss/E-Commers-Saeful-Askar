<?php

use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Enums\SyncType;
use App\Exceptions\JubelioApiException;
use App\Models\SyncLog;

/*
| sync_logs writer (ERD, ADM-10, ADM-14): one row per sync or order push, with
| Jubelio's error text kept verbatim (INT-HDL).
*/

test('track() records a successful run with the item count it returns', function () {
    $this->freezeSecond();

    $log = SyncLog::track(SyncType::Products, SyncTrigger::Manual, fn () => 42);

    expect($log->fresh())
        ->status->toBe(SyncStatus::Success)
        ->trigger->toBe(SyncTrigger::Manual)
        ->items_processed->toBe(42)
        ->started_at->toEqual(now())
        ->finished_at->toEqual(now());
});

test('track() records a failure with the Jubelio error verbatim and rethrows it', function () {
    $error = new JubelioApiException('jubelio', 'POST', '/sales/orders/', 500, providerCode: '23505', providerMessage: 'duplicate key value');

    expect(fn () => SyncLog::track(SyncType::OrderPush, SyncTrigger::Checkout, function (SyncLog $log) use ($error) {
        $log->update(['items_processed' => 1]);

        throw $error;
    }, referenceId: 7))->toThrow(JubelioApiException::class);

    expect(SyncLog::sole())
        ->status->toBe(SyncStatus::Failed)
        ->reference_id->toBe(7)
        ->items_processed->toBe(1)
        ->message->toBe('jubelio POST /sales/orders/ → HTTP 500 [23505] duplicate key value')
        ->finished_at->not->toBeNull();
});
