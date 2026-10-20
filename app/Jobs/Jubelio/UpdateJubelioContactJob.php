<?php

namespace App\Jobs\Jubelio;

use App\Jobs\Concerns\RetriesIntegrationCalls;
use App\Models\User;
use App\Services\Jubelio\ContactService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AUTH-06: sends a changed name or phone to the customer's Jubelio contact (INT-JB-09), off the request
 * so a slow Jubelio never delays saving the profile. It sends the profile as it is when the job runs,
 * so several edits in a row end with the latest one. The profile itself is already saved either way.
 */
class UpdateJubelioContactJob implements ShouldQueue
{
    use Queueable, RetriesIntegrationCalls;

    public function __construct(public int $userId) {}

    public function handle(ContactService $contacts): void
    {
        $user = User::find($this->userId);

        if ($user !== null) {
            $contacts->sync($user);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::channel('integrations')->error("Kontak Jubelio user #{$this->userId} gagal diperbarui: {$e->getMessage()}");
    }
}
