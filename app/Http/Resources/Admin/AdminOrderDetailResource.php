<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Api\V1\OrderDetailResource;
use App\Models\Order;
use App\Models\SyncLog;
use Illuminate\Http\Request;

/**
 * ADM-10: the customer's OrderDetail plus what only an admin needs (who, the item ids, who changed each
 * status, and the Gapura / Jubelio references with the sync history). No credentials or tokens appear here.
 *
 * @mixin Order
 */
class AdminOrderDetailResource extends OrderDetailResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        $data['customer'] = [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'phone' => $this->user->phone,
        ];
        $data['items'] = $this->items->map(fn ($item) => ['id' => $item->id, 'variant_id' => $item->product_variant_id] + $data['items'][$this->items->search($item)])->values()->all();
        $data['status_history'] = $this->statusHistories->map(fn ($history) => [
            'status' => $history->status->value,
            'label' => $history->status->label(),
            'at' => $history->created_at,
            'source' => $history->source->value,
            'note' => $history->note,
        ])->values()->all();
        $data['integration'] = [
            'jubelio_salesorder_id' => $this->jubelio_salesorder_id,
            'jubelio_salesorder_no' => $this->jubelio_salesorder_no,
            'jubelio_sync_status' => $this->jubelio_sync_status->value,
            'jubelio_last_error' => $this->jubelio_last_error,
            'gapura_reference_no' => $this->payment?->gapura_reference_no,
            'partner_reference_no' => $this->payment?->partner_reference_no ?? $this->order_number,
            'sync_logs' => SyncLog::query()
                ->where('reference_id', $this->id)
                ->whereIn('type', ['order_push', 'order_paid', 'order_cancel'])
                ->latest('started_at')->latest('id')
                ->get()
                ->map(fn (SyncLog $log) => [
                    'id' => $log->id,
                    'type' => $log->type->value,
                    'status' => $log->status->value,
                    'message' => $log->message,
                    'started_at' => $log->started_at,
                    'finished_at' => $log->finished_at,
                ])->all(),
        ];
        $data['is_checkout_failed'] = $this->is_checkout_failed;

        return $data;
    }
}
