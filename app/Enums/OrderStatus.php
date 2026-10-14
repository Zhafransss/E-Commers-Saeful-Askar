<?php

namespace App\Enums;

/**
 * Website order lifecycle (docs/api/README.md "Status order website").
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Menunggu pembayaran',
            self::Paid => 'Dibayar',
            self::Processing => 'Diproses gudang',
            self::Shipped => 'Dikirim',
            self::Completed => 'Selesai',
            self::Expired => 'Kedaluwarsa',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Valid transitions from the contract. Jubelio-driven updates only move
     * forward; cancelling paid/processing orders only happens from Jubelio.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Paid, self::Expired, self::Cancelled],
            self::Paid => [self::Processing, self::Shipped, self::Completed, self::Cancelled],
            self::Processing => [self::Shipped, self::Completed, self::Cancelled],
            self::Shipped => [self::Completed],
            self::Completed, self::Expired, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}
