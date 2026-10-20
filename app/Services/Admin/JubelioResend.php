<?php

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Enums\SyncStatus;
use App\Enums\SyncType;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\SyncLog;
use Illuminate\Validation\ValidationException;

/**
 * ADM-11: decides what sending an order to Jubelio again means. Without `action` it is chosen from the
 * order: no sales order → create; paid but never marked paid → mark_paid; expired/cancelled but never
 * cancelled → cancel. Anything already reflected in Jubelio is `ALREADY_SYNCED`.
 */
class JubelioResend
{
    private const PAID = [OrderStatus::Paid, OrderStatus::Processing, OrderStatus::Shipped, OrderStatus::Completed];

    private const CLOSED = [OrderStatus::Expired, OrderStatus::Cancelled];

    /**
     * @return 'create'|'mark_paid'|'cancel'
     */
    public function resolve(Order $order, ?string $requested): string
    {
        $action = $requested ?? $this->automatic($order);

        match ($action) {
            'create' => $this->assertCanCreate($order),
            'mark_paid' => $this->assertCanMarkPaid($order),
            'cancel' => $this->assertCanCancel($order),
        };

        return $action;
    }

    private function automatic(Order $order): string
    {
        if ($order->jubelio_salesorder_id === null) {
            return 'create';
        }

        if (in_array($order->status, self::PAID, true) && ! $this->done($order, SyncType::OrderPaid)) {
            return 'mark_paid';
        }

        if (in_array($order->status, self::CLOSED, true) && ! $this->done($order, SyncType::OrderCancel)) {
            return 'cancel';
        }

        throw $this->alreadySynced($order);
    }

    private function assertCanCreate(Order $order): void
    {
        if ($order->jubelio_salesorder_id !== null) {
            throw $this->alreadySynced($order);
        }

        // A checkout that failed was cancelled on purpose: its sales order must not come back.
        if ($order->is_checkout_failed || in_array($order->status, self::CLOSED, true)) {
            throw $this->mismatch('Pesanan yang sudah dibatalkan atau kedaluwarsa tidak dikirim sebagai sales order baru.');
        }
    }

    private function assertCanMarkPaid(Order $order): void
    {
        if (! in_array($order->status, self::PAID, true)) {
            throw $this->mismatch('Pesanan belum dibayar, jadi tidak bisa ditandai lunas di Jubelio.');
        }

        if ($order->jubelio_salesorder_id === null) {
            throw $this->mismatch('Pesanan belum punya sales order di Jubelio. Kirim dengan action "create" dulu.');
        }

        if ($this->done($order, SyncType::OrderPaid)) {
            throw $this->alreadySynced($order);
        }
    }

    private function assertCanCancel(Order $order): void
    {
        if (! in_array($order->status, self::CLOSED, true)) {
            throw $this->mismatch('Hanya pesanan yang dibatalkan atau kedaluwarsa yang bisa dibatalkan di Jubelio.');
        }

        if ($order->jubelio_salesorder_id === null) {
            throw $this->mismatch('Pesanan ini tidak punya sales order di Jubelio.');
        }

        if ($this->done($order, SyncType::OrderCancel)) {
            throw $this->alreadySynced($order);
        }
    }

    private function done(Order $order, SyncType $type): bool
    {
        return SyncLog::where('reference_id', $order->id)->where('type', $type->value)->where('status', SyncStatus::Success->value)->exists();
    }

    private function alreadySynced(Order $order): ApiException
    {
        $number = $order->jubelio_salesorder_no ?? $order->jubelio_salesorder_id;

        return new ApiException("Pesanan ini sudah tercatat di Jubelio ({$number}).", 'ALREADY_SYNCED', 409);
    }

    private function mismatch(string $message): ValidationException
    {
        return ValidationException::withMessages(['action' => [$message]]);
    }
}
